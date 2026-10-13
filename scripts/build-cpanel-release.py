"""Create a secret-free cPanel staging tree, then zip it after vendor installation.

Usage: python scripts/build-cpanel-release.py stage
       composer install --working-dir=storage/app/cpanel-release/sbispgm --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
       python scripts/build-cpanel-release.py zip
"""
import argparse
import hashlib
import json
from pathlib import Path
import re
import shutil
import zipfile

ROOT = Path(__file__).resolve().parents[1]
STAGE = ROOT / 'storage/app/cpanel-release'
APP = STAGE / 'sbispgm'
WEB = STAGE / 'public_html'
ARCHIVE = ROOT / 'storage/app/sbispgm-cpanel-release.zip'


def stage():
    if STAGE.exists():
        raise SystemExit('Staging folder already exists. Review or rename it before building a new release.')
    if not (ROOT / 'public/build/manifest.json').is_file():
        raise SystemExit('Run npm run build before staging.')
    APP.mkdir(parents=True)
    for directory in ['app', 'config', 'routes', 'resources']:
        shutil.copytree(ROOT / directory, APP / directory)
    for directory in ['migrations', 'seeders']:
        shutil.copytree(ROOT / 'database' / directory, APP / 'database' / directory)
    for filename in ['artisan', 'composer.json', 'composer.lock', '.env.production.example']:
        shutil.copy2(ROOT / filename, APP / filename)
    # Production package should never autoload development factories/tests.
    composer = json.loads((APP / 'composer.json').read_text(encoding='utf-8'))
    composer.pop('autoload-dev', None)
    (APP / 'composer.json').write_text(json.dumps(composer, indent=4) + '\n', encoding='utf-8')
    (APP / 'bootstrap/cache').mkdir(parents=True)
    shutil.copy2(ROOT / 'bootstrap/app.php', APP / 'bootstrap/app.php')
    for directory in ['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']:
        (APP / 'storage' / directory).mkdir(parents=True)
    (APP / 'public').mkdir()
    shutil.copytree(ROOT / 'public', WEB, ignore=shutil.ignore_patterns('hot', 'storage', 'downloads'))
    index = (WEB / 'index.php').read_text(encoding='utf-8')
    index = index.replace("__DIR__.'/../storage/", "__DIR__.'/../sbispgm/storage/")
    index = index.replace("__DIR__.'/../vendor/", "__DIR__.'/../sbispgm/vendor/")
    index = index.replace("__DIR__.'/../bootstrap/", "__DIR__.'/../sbispgm/bootstrap/")
    index = index.replace('$kernel = $app->make(Kernel::class);', '$app->usePublicPath(__DIR__);\n\n$kernel = $app->make(Kernel::class);')
    (WEB / 'index.php').write_text(index, encoding='utf-8')
    for source, target in [('cpanel-deployment.md', 'DEPLOYMENT.md'),
                           ('internal-test-report.md', 'INTERNAL-TEST-REPORT.md')]:
        guide = ROOT / 'docs' / source
        if guide.is_file():
            shutil.copy2(guide, STAGE / target)
    print(f'Staged: {STAGE}')
    print('Install production vendor dependencies into sbispgm, then run this script with zip.')


def package():
    if not (APP / 'vendor/autoload.php').is_file():
        raise SystemExit('Install production vendor dependencies before packaging.')
    installed = json.loads((APP / 'vendor/composer/installed.json').read_text(encoding='utf-8'))
    if installed.get('dev', True):
        raise SystemExit('Vendor contains development dependencies. Install with --no-dev.')
    platform = (APP / 'vendor/composer/platform_check.php').read_text(encoding='utf-8')
    minimum = re.search(r'PHP_VERSION_ID >= (\d+)', platform)
    if minimum is None or int(minimum.group(1)) > 80200:
        raise SystemExit('Production dependencies exceed the documented PHP 8.2 minimum; review the release requirements.')
    # Verify exclusions rather than trusting a recursive copy of the project.
    forbidden = [APP / '.env', APP / 'node_modules', APP / 'tests', APP / 'database/database.sqlite',
                 APP / 'bootstrap/cache/config.php', WEB / '.env', WEB / 'storage', WEB / 'hot']
    if any(path.exists() for path in forbidden):
        raise SystemExit('Package contains a private or development artifact; review staging.')
    if any(path.is_file() for path in (APP / 'storage').rglob('*')):
        raise SystemExit('Package storage must contain no local records, logs, or cached files.')
    with zipfile.ZipFile(ARCHIVE, 'w', zipfile.ZIP_DEFLATED) as archive:
        for path in sorted(STAGE.rglob('*')):
            if path.is_file():
                archive.write(path, path.relative_to(STAGE).as_posix())
            elif not any(path.iterdir()):
                archive.writestr(path.relative_to(STAGE).as_posix() + '/', '')
    print(f'Created: {ARCHIVE} ({ARCHIVE.stat().st_size:,} bytes)')
    digest = hashlib.sha256()
    with ARCHIVE.open('rb') as release:
        for chunk in iter(lambda: release.read(1024 * 1024), b''):
            digest.update(chunk)
    ARCHIVE.with_suffix('.zip.sha256').write_text(
        f'{digest.hexdigest()}  {ARCHIVE.name}\n', encoding='utf-8'
    )


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['stage', 'zip'])
    arguments = parser.parse_args()
    stage() if arguments.action == 'stage' else package()
