#!/usr/bin/env python3
"""Build a web-only cPanel upload ZIP from an explicit source allowlist."""
from pathlib import Path
import argparse
import re
import shutil
import zipfile
ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--output-dir', type=Path, default=ROOT/'dist', help='Archive destination (default: dist/)')
args = parser.parse_args()
version_match = re.search(r"define\('VERSION',\s*'([0-9]+\.[0-9]+\.[0-9]+)'\)", (ROOT/'meta.php').read_text())
if version_match is None:
    raise RuntimeError('Missing semantic release version in meta.php')
version = version_match.group(1)
files = [ROOT/name for name in ['.htaccess','index.php','admin.php','install.php','login.php','logout.php','header.php','footer.php','meta.php']]
for directory,names in {
    'lib':['adobeconnect.php','bootstrap.php','configuration.php','installer.php','migrate.php','schema.php','store.php','util.php'],
    'api':['initial.php','join.php','login.php','resource.php','server-item.php','server-list.php'],
}.items():
    files.extend(ROOT/directory/name for name in names)
allowed = {'.js','.css','.map','.woff','.woff2','.eot','.ttf','.svg','.png','.jpg','.txt'}
for directory in ['js','css','fonts','images','plugins']:
    files.extend(p for p in (ROOT/directory).rglob('*') if p.is_file() and not p.is_symlink() and p.suffix in allowed)
output = args.output_dir/'bams-cpanel.zip'
output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as archive:
    for file in sorted(files):
        if not file.is_file() or file.is_symlink():
            raise RuntimeError('Missing/nonregular distributable source: '+str(file.relative_to(ROOT)))
        archive.write(file,str(file.relative_to(ROOT)))
    archive.write(ROOT/'docs'/'cpanel-install.md','INSTALL.txt')
    archive.write(ROOT/'LICENSE','LICENSE')
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    entries = set(archive.namelist())
    assert {'index.php','install.php','lib/installer.php','lib/schema.php','js/install.js','INSTALL.txt'} <= entries
    assert not any(Path(name).name in {'config.php','bootstrap.example.php','setup.key','bootstrap.php'} and name != 'lib/bootstrap.php' for name in entries)
    assert not any(name.startswith(('tests/','bin/','var/','config/')) or name.endswith(('.sqlite','.log','.bak')) for name in entries)
versioned_output = output.with_name(f'bams-cpanel-{version}.zip')
shutil.copyfile(output, versioned_output)
print(f'Built {output} and {versioned_output}; source allowlist and archive integrity checks passed.')
