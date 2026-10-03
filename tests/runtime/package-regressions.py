"""Verify exact ZIP validation rejects missing command runtime and development files."""
import argparse, json, subprocess, tempfile, zipfile
from pathlib import Path
parser=argparse.ArgumentParser()
parser.add_argument('package',type=Path)
args=parser.parse_args()
validator=Path(__file__).resolve().parents[2]/'scripts/validate-release-package.php'
def validate(path):
    return subprocess.run(['php',str(validator),str(path)],capture_output=True,text=True)
good=validate(args.package)
assert good.returncode==0,(good.stdout,good.stderr)
reports=[dict(probe='baseline package',passed=True)]
with tempfile.TemporaryDirectory(prefix='cleanlinks-116-package-') as directory:
    for name,extra,missing in [('docs','docs/1.2/internal.md',None),('design','DESIGN.md',None),('tests','tests/runtime/internal.php',None),('missing-command',None,'src/Application/LinkCommands.php')]:
        altered=Path(directory)/(name+'.zip')
        with zipfile.ZipFile(args.package) as source,zipfile.ZipFile(altered,'w',zipfile.ZIP_DEFLATED) as dest:
            for entry in source.infolist():
                if entry.filename!=missing:dest.writestr(entry,source.read(entry.filename))
            if extra:dest.writestr(extra,'development content')
        result=validate(altered)
        assert result.returncode==1 and (extra or missing) in result.stderr,(name,result.stdout,result.stderr)
        reports.append(dict(probe=name,passed=True,rejected=True))
print(json.dumps(reports,indent=2))
