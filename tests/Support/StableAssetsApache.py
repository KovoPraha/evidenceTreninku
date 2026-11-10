"""Two real releases on each owned loopback origin, using production cache rules."""
from WorkspaceAssetsApache import WorkspaceAssetsApache
import os
import secrets
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer


class StableAssetsApache(WorkspaceAssetsApache):
    def __init__(self, runtime, root, paths):
        super().__init__(runtime, root)
        self.paths = paths
        self.control = None
        self.token = secrets.token_hex(32)

    def start(self):
        # Extend the existing real Apache fixture, preserving its TLS-free
        # loopback-only binding, production .htaccess and compression settings.
        super().start()
        self.stop()
        for variant, backends in [('before', (18197, 18199)), ('after', (18198, 18200))]:
            path = self.runtime / ('apache-' + variant) / 'public/.htaccess'
            rules = path.read_text()
            marker = '\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^$ http://127.0.0.1:'
            assert rules.count(marker) == 1
            prefix, suffix = rules.split(marker)
            extra = '\n<IfModule mod_rewrite.c>\nRewriteEngine On\n'
            for release, backend in zip(['r1', 'r2'], backends):
                extra += f'RewriteRule ^{release}/(.*\\.php)$ http://127.0.0.1:{backend}/$1 [P,L]\n'
            path.write_text(prefix + extra + 'RewriteRule ^$ http://127.0.0.1:' + suffix)
        # Restart with the already-created physical roots, without recopying.
        import subprocess, socket, time
        for variant, port in [('before', 18099), ('after', 18100)]:
            owned = self.runtime / ('apache-' + variant)
            log = (owned / 'restart.log').open('wb')
            process = subprocess.Popen(['apache2', '-X', '-f', str(owned / 'apache.conf')], stdout=log, stderr=log)
            self.processes.append((process, log, port))
            for _ in range(100):
                assert process.poll() is None, 'Owned Apache failed'
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=.2): break
                except OSError: time.sleep(.1)
            else: raise RuntimeError('Owned Apache unavailable')
        owner = self

        class Control(BaseHTTPRequestHandler):
            def log_message(self, *args): pass

            def do_POST(self):
                if self.headers.get('Authorization') != 'Bearer ' + owner.token:
                    self.send_error(403); return
                parts = self.path.strip('/').split('/')
                if len(parts) != 2 or parts[0] not in ['before', 'after'] or parts[1] not in ['r1', 'r2', 'content', 'broken']:
                    self.send_error(400); return
                variant, release = parts
                if release == 'content':
                    assert variant == 'after'
                    import subprocess, shutil
                    document = owner.runtime / 'after-r2'
                    target = document / 'assets/app-ui.css'
                    target.write_bytes(target.read_bytes() + b'\n/* Owned content invalidation acceptance. */\n')
                    subprocess.run(['php', str(owner.root / 'tools/build-ui-asset-versions.php'), '--write', '--root=' + str(document)], check=True)
                    shutil.copy2(target, owner.runtime / 'apache-after/public/assets/app-ui.css')
                    self.send_response(204); self.end_headers(); return
                if release == 'broken':
                    image = owner.runtime / ('apache-' + variant) / 'public/uploads/shop-products/00000000000000000000000000000001.jpg'
                    assert image.resolve().is_relative_to(owner.runtime.resolve())
                    image.write_bytes(b'Owned deliberately invalid JPEG')
                    self.send_response(204); self.end_headers(); return
                stamp = 1794302581 if release == 'r1' else 1794311532
                public = owner.runtime / ('apache-' + variant) / 'public'
                assert public.resolve().is_relative_to(owner.runtime.resolve())
                for path in owner.paths:
                    target = public / path
                    assert target.is_file() and not target.is_symlink()
                    os.utime(target, (stamp, stamp))
                self.send_response(204); self.end_headers()

        self.control = ThreadingHTTPServer(('127.0.0.1', 0), Control)
        threading.Thread(target=self.control.serve_forever, daemon=True).start()

    def stop(self):
        if self.control:
            self.control.shutdown(); self.control.server_close(); self.control = None
        super().stop()
