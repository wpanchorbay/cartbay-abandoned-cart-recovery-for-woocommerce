/*
 * Release packager for CartBay.
 *
 * Creates:
 * - dist/cartbay/  (staging folder)
 * - cartbay.zip    (or cartbay-<version>.zip with --versioned)
 */

const path = require('path');
const fs = require('fs-extra');
const archiver = require('archiver');
const { execSync } = require('child_process');

const PLUGIN_SLUG = 'cartbay';
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

async function strip_composer_dev_sections(composer_path) {
	const composer_data = await fs.readJson(composer_path);
	delete composer_data['require-dev'];
	delete composer_data['autoload-dev'];
	delete composer_data['scripts'];
	delete composer_data['config'];
	await fs.writeJson(composer_path, composer_data, { spaces: '\t' });
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

		await copy_if_exists(path.join(ROOT_DIR, 'cartbay.php'), path.join(STAGE_DIR, 'cartbay.php'));
		await copy_if_exists(path.join(ROOT_DIR, 'uninstall.php'), path.join(STAGE_DIR, 'uninstall.php'));
		await copy_if_exists(path.join(ROOT_DIR, 'readme.txt'), path.join(STAGE_DIR, 'readme.txt'));
		await copy_if_exists(path.join(ROOT_DIR, 'LICENSE.txt'), path.join(STAGE_DIR, 'LICENSE.txt'));

		// Composer files (vendor will be generated in staging)
		await copy_if_exists(path.join(ROOT_DIR, 'composer.json'), path.join(STAGE_DIR, 'composer.json'));
		await copy_if_exists(path.join(ROOT_DIR, 'composer.lock'), path.join(STAGE_DIR, 'composer.lock'));

		// 5. Composer production deps
		if (!skip_composer) {
			console.log('📦 Installing production Composer dependencies...');
			execSync('composer install --no-dev --optimize-autoloader', {
				stdio: 'inherit',
				cwd: STAGE_DIR,
			});

			console.log('📝 Cleaning composer.json for production...');
			await strip_composer_dev_sections(path.join(STAGE_DIR, 'composer.json'));
		} else {
			console.log('↪️  Skipping composer install (--skip-composer).');
		}

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
