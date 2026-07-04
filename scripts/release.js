/*
 * Release packager for CartBay.
 *
 * Creates:
 * - dist/cartbay-abandoned-cart-recovery-for-woocommerce/  (staging folder)
 * - cartbay-abandoned-cart-recovery-for-woocommerce.zip    (or -<version>.zip with --versioned)
 */

const path = require('path');
const fs = require('fs-extra');
const archiver = require('archiver');
const { execSync } = require('child_process');

const PLUGIN_SLUG = 'cartbay-abandoned-cart-recovery-for-woocommerce';
const ROOT_DIR = path.join(__dirname, '..');
const DIST_DIR = path.join(ROOT_DIR, 'dist');
const STAGE_DIR = path.join(DIST_DIR, PLUGIN_SLUG);

const package_json = require(path.join(ROOT_DIR, 'package.json'));

const args = new Set(process.argv.slice(2));
const include_version = args.has('--versioned');
const skip_build = args.has('--skip-build');
const skip_i18n = args.has('--skip-i18n');
const skip_composer = args.has('--skip-composer');

const zip_name = include_version
	? `${PLUGIN_SLUG}-${package_json.version}.zip`
	: `${PLUGIN_SLUG}.zip`;

function detect_package_manager() {
	const ua = process.env.npm_config_user_agent || '';
	if (ua.includes('bun')) {
		return 'bun';
	}
	if (ua.includes('pnpm')) {
		return 'pnpm';
	}
	if (ua.includes('yarn')) {
		return 'yarn';
	}
	return 'npm';
}

function run_pkg_script(script_name) {
	const pm = detect_package_manager();
	const cmd =
		pm === 'bun'
			? `bun run ${script_name}`
			: pm === 'yarn'
				? `yarn ${script_name}`
				: pm === 'pnpm'
					? `pnpm run ${script_name}`
					: `npm run ${script_name}`;
	execSync(cmd, { stdio: 'inherit', cwd: ROOT_DIR });
}

async function copy_if_exists(src, dest) {
	if (await fs.pathExists(src)) {
		await fs.copy(src, dest);
	}
}

(async () => {
	try {
		console.log('🚀 Starting CartBay release build...');

		// 1. Clean
		console.log('🧹 Cleaning old build artifacts...');
		await fs.remove(DIST_DIR);
		await fs.remove(path.join(ROOT_DIR, zip_name));
		await fs.ensureDir(STAGE_DIR);

		// 2. i18n
		if (!skip_i18n) {
			console.log('🌐 Generating i18n files...');
			run_pkg_script('i18n:make-pot');
			run_pkg_script('i18n:make-json');
		} else {
			console.log('↪️  Skipping i18n generation (--skip-i18n).');
		}

		// 3. Build assets
		if (!skip_build) {
			console.log('🛠  Building assets...');
			run_pkg_script('build');
		} else {
			console.log('↪️  Skipping JS build (--skip-build).');
		}

		// 4. Copy plugin files
		console.log('📂 Copying plugin files to staging...');
		await copy_if_exists(path.join(ROOT_DIR, 'app'), path.join(STAGE_DIR, 'app'));
		await copy_if_exists(path.join(ROOT_DIR, 'assets'), path.join(STAGE_DIR, 'assets'));
		await copy_if_exists(path.join(ROOT_DIR, 'languages'), path.join(STAGE_DIR, 'languages'));
		await copy_if_exists(path.join(ROOT_DIR, 'templates'), path.join(STAGE_DIR, 'templates'));

		await copy_if_exists(path.join(ROOT_DIR, `${PLUGIN_SLUG}.php`), path.join(STAGE_DIR, `${PLUGIN_SLUG}.php`));
		await copy_if_exists(path.join(ROOT_DIR, 'uninstall.php'), path.join(STAGE_DIR, 'uninstall.php'));
		await copy_if_exists(path.join(ROOT_DIR, 'readme.txt'), path.join(STAGE_DIR, 'readme.txt'));
		await copy_if_exists(path.join(ROOT_DIR, 'LICENSE.txt'), path.join(STAGE_DIR, 'LICENSE.txt'));

		// Composer files are staged only so `composer install` can generate
		// vendor/ below; they are removed again before zipping (see step 5).
		await copy_if_exists(path.join(ROOT_DIR, 'composer.json'), path.join(STAGE_DIR, 'composer.json'));
		await copy_if_exists(path.join(ROOT_DIR, 'composer.lock'), path.join(STAGE_DIR, 'composer.lock'));

		// 5. Composer production deps
		if (!skip_composer) {
			console.log('📦 Installing production Composer dependencies...');
			execSync('composer install --no-dev --optimize-autoloader', {
				stdio: 'inherit',
				cwd: STAGE_DIR,
			});
		} else {
			console.log('↪️  Skipping composer install (--skip-composer).');
		}

		// Drop dev metadata: composer.json/lock are only needed to build
		// vendor/ above and are never loaded at runtime, so they must not
		// ship in the WordPress.org package.
		console.log('🧽 Removing composer metadata from the package...');
		await fs.remove(path.join(STAGE_DIR, 'composer.json'));
		await fs.remove(path.join(STAGE_DIR, 'composer.lock'));

		// 6. Create zip
		console.log(`🤐 Creating zip: ${zip_name} ...`);
		const out_path = path.join(ROOT_DIR, zip_name);
		const output = fs.createWriteStream(out_path);
		const archive = archiver('zip', { zlib: { level: 9 } });

		output.on('close', () => {
			console.log(`✅ Success! Created ${zip_name} (${archive.pointer()} bytes)`);
		});

		archive.on('error', (err) => {
			throw err;
		});

		archive.pipe(output);
		// Put the plugin folder inside the zip (WordPress expects this)
		archive.directory(STAGE_DIR, PLUGIN_SLUG);
		await archive.finalize();
	} catch (err) {
		console.error('❌ Release build failed:', err);
		process.exit(1);
	}
})();
