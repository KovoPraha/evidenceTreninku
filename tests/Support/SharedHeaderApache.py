"""Owned loopback Apache front ends; PHP stays on its existing private workers."""
from pathlib import Path
import getpass
import grp
import http.client
import os
import shutil
import socket
import subprocess
import time


class SharedHeaderApache:
    def __init__(self, runtime, root):
        self.runtime, self.root = runtime, root
        self.processes = []

    def start(self):
        apache = shutil.which('apache2')
        assert apache and os.name != 'nt'
        compiled = subprocess.check_output([apache, '-l'], text=True)
        modules = ['authz_core', 'authz_host', 'mime', 'filter', 'deflate', 'headers', 'rewrite', 'proxy', 'proxy_http']
        if 'mpm_' not in compiled:
            modules.insert(0, 'mpm_event')
        if 'unixd.c' not in compiled:
            modules.insert(0, 'unixd')
        loads = '\n'.join(f'LoadModule {m}_module /usr/lib/apache2/modules/mod_{m}.so'
                          for m in modules if f'mod_{m}.c' not in compiled)
        for variant, port, backend in [('before', 18099, 18199), ('after', 18100, 18200)]:
            owned = self.runtime / ('apache-' + variant)
            public = owned / 'public'
            public.mkdir(parents=True)
            # Physical copies: the new asset must not leak into the old variant.
            shutil.copytree(self.root / 'assets', public / 'assets',
                            ignore=shutil.ignore_patterns('staff-header.*.js') if variant == 'before' else None)
            if variant == 'before':
                (public / 'assets/.htaccess').write_bytes(subprocess.check_output(['git','show','5d6434dc6d4338d47e283d715a4b2c568950c986:assets/.htaccess'],cwd=self.root))
            for name in ['sw.js', 'manifest.json', 'icon-192.png', 'icon-512.png', 'icon-72.png']:
                if (self.root / name).exists():
                    shutil.copy2(self.root / name, public / name)
            rules = (self.root / '.htaccess').read_text()
            rules += f'''\n<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^$ http://127.0.0.1:{backend}/index.php [P,L]
RewriteRule ^(.*\\.php)$ http://127.0.0.1:{backend}/$1 [P,L]
</IfModule>\n'''
            (public / '.htaccess').write_text(rules)
            config = f'''ServerRoot "{owned}"
{loads}
User {getpass.getuser()}
Group {grp.getgrgid(os.getgid()).gr_name}
Listen 127.0.0.1:{port}
ServerName 127.0.0.1
PidFile "{owned}/apache.pid"
ErrorLog "{owned}/error.log"
LogLevel warn
DocumentRoot "{public}"
TypesConfig /etc/mime.types
AddType text/javascript .js
ProxyRequests Off
ProxyPreserveHost On
<Directory />
AllowOverride None
</Directory>
<Directory "{public}">
AllowOverride All
Require all granted
</Directory>
<FilesMatch "\\.js$">
Header set Cache-Control "max-age=30"
</FilesMatch>
<FilesMatch "\\.css$">
Header set Cache-Control "max-age=604800"
</FilesMatch>
AddOutputFilterByType DEFLATE text/html text/css
'''
            path = owned / 'apache.conf'
            path.write_text(config)
            subprocess.run([apache, '-t', '-f', str(path)], check=True, capture_output=True)
            log = (owned / 'process.log').open('wb')
            process = subprocess.Popen([apache, '-X', '-f', str(path)], stdout=log, stderr=log)
            self.processes.append((process, log, port))
            for _ in range(100):
                if process.poll() is not None:
                    raise RuntimeError('Owned Apache exited: ' + (owned / 'process.log').read_text())
                try:
                    connection = http.client.HTTPConnection('127.0.0.1', port, timeout=2)
                    connection.request('GET', '/assets/app-ui.js', headers={'Accept-Encoding': 'gzip'})
                    response = connection.getresponse()
                    body = response.read()
                    assert response.status == 200 and response.getheader('Content-Encoding') == 'gzip' and body
                    assert response.getheader('Cache-Control') == 'max-age=30'
                    connection.close()
                    break
                except OSError:
                    time.sleep(.1)
            else:
                raise RuntimeError('Owned Apache unavailable')

    def stop(self):
        while self.processes:
            process, log, port = self.processes.pop()
            process.terminate()
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait(timeout=10)
            log.close()
            with socket.socket() as connection:
                assert connection.connect_ex(('127.0.0.1', port)) != 0, 'Owned Apache port still open'
