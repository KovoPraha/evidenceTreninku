import copy
import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('audit', ROOT/'tools/publish-membership-audit.py')
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)
FLAGS = {'schedule': 'false', 'apply': 'true', 'mail': 'true'}


class PublicationTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        for name in audit.DOCS + ['docs/CURRENT_STATE.md', 'docs/CHANGELOG-PROVOZ.md']:
            target = self.root/name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text('Stav: NOT RUN\npříští ranní běh zatím nelze doložit.\n', encoding='utf-8')

    def tearDown(self):
        self.temp.cleanup()

    def test_preflight_requires_natural_tick_and_cannot_claim_morning(self):
        data = {'ok': True, 'checked_at_utc': '2026-11-06T18:00:02+00:00', 'installed_expected_source': True,
                'heartbeat': {'last_tick_at': 1793988000, 'phase': 'waiting', 'trigger': 'hosting_cron'},
                'membership': {'recent_hosting_tick': True, 'automation_enabled': True, 'mail_enabled': True}}
        self.assertEqual('PASS', audit.generate(self.root, 'preflight', data, FLAGS, '123'))
        path = self.root/(audit.OUTPUTS['preflight']+'/audit.json')
        self.assertIn('NOT RUN', path.read_text())
        original = path.read_bytes()
        data['heartbeat'] = {}
        with self.assertRaises(ValueError):
            audit.generate(self.root, 'preflight', data, FLAGS, '124')
        self.assertEqual(original, path.read_bytes())

    def test_publish_refuses_premature_capture_and_preserves_failure(self):
        report = {'version': 1, 'target_day': '2026-11-07', 'trigger': 'hosting_cron', 'verdict': 'FAIL',
                  'captured_at_utc': '2026-11-07T05:30:02+00:00', 'actual_attempt_utc': None,
                  'start_delay_seconds': None, 'last_success_utc': None, 'first_attempt_limitation': True}
        data = {'ok': True, 'checked_at_utc': '2026-11-07T06:00:00+00:00', 'report': report}
        bad = copy.deepcopy(data)
        bad['report']['captured_at_utc'] = '2026-11-06T05:30:00+00:00'
        with self.assertRaises(ValueError):
            audit.generate(self.root, 'publish', bad, FLAGS, '123')
        self.assertEqual('FAIL', audit.generate(self.root, 'publish', data, FLAGS, '123'))
        state = (self.root/'docs/CURRENT_STATE.md').read_text(encoding='utf-8')
        self.assertIn('[FAIL]', state)
        self.assertNotIn('příští ranní běh zatím nelze doložit.', state)
        self.assertIn('<!-- archive ', (self.root/'docs/CHANGELOG-PROVOZ.md').read_text())
        for name in audit.DOCS:
            self.assertIn('P05/P07', (self.root/name).read_text(encoding='utf-8'))

    def test_unknown_mode_and_unsafe_run_id_do_not_write(self):
        before = (self.root/audit.DOCS[0]).read_bytes()
        for mode, run in [('install', '123'), ('publish', '$(evil)')]:
            with self.assertRaises(ValueError):
                audit.generate(self.root, mode, {'ok': True}, FLAGS, run)
        self.assertEqual(before, (self.root/audit.DOCS[0]).read_bytes())

    def test_forged_pass_without_checks_is_rejected(self):
        data = {'ok': True, 'checked_at_utc': '2026-11-07T06:00:00+00:00',
                'report': {'version': 1, 'target_day': '2026-11-07', 'trigger': 'hosting_cron', 'verdict': 'PASS',
                           'captured_at_utc': '2026-11-07T05:30:02+00:00'}}
        with self.assertRaises(ValueError):
            audit.generate(self.root, 'publish', data, FLAGS, '123')

    def test_timely_capture_publishes_pass_and_cet_start(self):
        report = {'version': 1, 'target_day': '2026-11-07', 'trigger': 'hosting_cron', 'verdict': 'PASS',
                  'captured_at_utc': '2026-11-07T05:30:02+00:00', 'actual_attempt_utc': '2026-11-07T05:23:02+00:00',
                  'actual_start_utc': '2026-11-07T05:23:03+00:00', 'start_delay_seconds': 3,
                  'last_success_utc': '2026-11-07T05:23:04+00:00', 'first_attempt_limitation': False,
                  'checks': {str(n): True for n in range(13)}}
        data = {'ok': True, 'checked_at_utc': '2026-11-07T06:00:00+00:00', 'report': report}
        self.assertEqual('PASS', audit.generate(self.root, 'publish', data, FLAGS, '123'))
        note = (self.root/(audit.OUTPUTS['publish']+'/RESULTS.md')).read_text(encoding='utf-8')
        self.assertIn('06:23:03+01:00', note)
        self.assertIn('odchylka 3 s', note)


if __name__ == '__main__':
    unittest.main()
