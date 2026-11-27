import importlib.util
import json
import pathlib
import tempfile
import unittest

SCRIPT = pathlib.Path(__file__).resolve().parents[1] / "server-storage-plan.py"
SPEC = importlib.util.spec_from_file_location("server_storage_plan", SCRIPT)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class StoragePlanTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = pathlib.Path(self.temp.name)
        self.items = []
        for number in range(280):
            sha = f"{number + 1:040x}"
            run_id = 1000 + number
            self.items.append(
                {
                    "path": f"data/.kis-deploy/releases/{sha}-{run_id}",
                    "sha": sha,
                    "run_id": run_id,
                    "kib": 100,
                    "disposition": "candidate",
                }
            )
        self.write("manifest.json", json.dumps({
            "schema": "server-storage-release-manifest-v1",
            "release_count": len(self.items), "items": self.items
        }))
        self.write("releases.tsv", "".join(
            f"{item['kib']}\t{item['path']}\n" for item in self.items
        ))
        self.backups = {
            "data/.kis-backups/files": 500,
            "data/.kis-backups/config": 4,
            "data/.kis-backups/keys": 4,
            "data/.kis-backups/private-storage": 4,
        }
        for number in range(20):
            for extension in ("sql.gz", "manifest.json", "sha256"):
                self.backups[f"data/.kis-backups/evidence_20261126_{number:06d}_abc.{extension}"] = 10
        for number in range(12):
            self.backups[f"data/.kis-backups/bar-url-{number}"] = 100
        self.write_backups()
        self.write("files.tsv", "".join(
            f"100\tdata/.kis-backups/files/kis-files-{number}.zip\n" for number in range(5)
        ))
        self.write("deployment.json", json.dumps({
            "sha": self.items[-1]["sha"], "run_id": self.items[-1]["run_id"]
        }))

    def write(self, name, content):
        (self.root / name).write_text(content, encoding="utf-8")

    def write_backups(self):
        self.write("backups.tsv", "".join(
            f"{size}\t{path}\n" for path, size in self.backups.items()
        ))

    def args(self):
        return type("Args", (), {
            name: str(self.root / f"{name}.{suffix}")
            for name, suffix in (
                ("manifest", "json"),
                ("releases", "tsv"),
                ("backups", "tsv"),
                ("files", "tsv"),
                ("deployment", "json"),
            )
        })()

    def test_exact_plan_retains_active_release_and_support_files(self):
        result = MODULE.plan(self.args())
        self.assertEqual(len(result["release_delete"]), 279)
        self.assertEqual(result["release_delete_kib"], 27900)
        self.assertEqual(result["active_release"], self.items[-1]["path"])
        self.assertNotIn(result["active_release"], {
            item["path"] for item in result["release_delete"]
        })
        self.assertEqual(set(result["backup_retain"]), MODULE.BACKUP_RETAIN)
        self.assertEqual(len(result["backup_delete"]), 73)
        script = MODULE.shell_script(result)
        self.assertIn("Preflight PASS", script)
        self.assertNotIn("rm -rf -- " + result["active_release"], script)

    def test_release_size_drift_aborts_before_delete(self):
        self.write("releases.tsv", (self.root / "releases.tsv").read_text().replace(
            "100\t" + self.items[0]["path"], "101\t" + self.items[0]["path"]
        ))
        with self.assertRaisesRegex(ValueError, "differ"):
            MODULE.plan(self.args())

    def test_new_deployment_requires_reviewed_manifest(self):
        new_item = {
            "path": "data/.kis-deploy/releases/" + "f" * 40 + "-2000",
            "sha": "f" * 40,
            "run_id": 2000,
            "kib": 120,
            "disposition": "keep",
        }
        self.write("releases.tsv", (self.root / "releases.tsv").read_text()
                   + f"120\t{new_item['path']}\n")
        self.write("deployment.json", json.dumps({
            "sha": new_item["sha"], "run_id": new_item["run_id"]
        }))
        with self.assertRaisesRegex(ValueError, "differ"):
            MODULE.plan(self.args())
        self.items.append(new_item)
        self.write("manifest.json", json.dumps({
            "schema": "server-storage-release-manifest-v1",
            "release_count": len(self.items), "items": self.items
        }))
        result = MODULE.plan(self.args())
        self.assertEqual(result["active_release"], new_item["path"])
        self.assertEqual(len(result["release_delete"]), 280)

    def test_unknown_backup_entry_aborts_before_delete(self):
        self.backups["data/.kis-backups/current-private-data"] = 200
        self.write_backups()
        with self.assertRaisesRegex(ValueError, "unclassified"):
            MODULE.plan(self.args())

    def test_old_deployment_marker_aborts_before_delete(self):
        self.write("deployment.json", json.dumps({
            "sha": self.items[0]["sha"], "run_id": self.items[0]["run_id"]
        }))
        with self.assertRaisesRegex(ValueError, "newest"):
            MODULE.plan(self.args())


if __name__ == "__main__":
    unittest.main()
