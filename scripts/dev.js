/*
 * Cross-platform dev runner.
 * - On Windows: runs scripts/dev.ps1
 * - Else: runs scripts/dev.sh
 */

const path = require('path');
const { spawnSync } = require('child_process');

const is_windows = process.platform === 'win32';
const scripts_dir = __dirname;

const args = process.argv.slice(2);

function run(cmd, cmd_args, options) {
	const result = spawnSync(cmd, cmd_args, { stdio: 'inherit', ...options });
	if (result.error) {
		throw result.error;
	}
	process.exitCode = result.status ?? 1;
}

try {
	if (is_windows) {
		const script = path.join(scripts_dir, 'dev.ps1');
		run('powershell', ['-ExecutionPolicy', 'Bypass', '-File', script, ...args]);
	} else {
		const script = path.join(scripts_dir, 'dev.sh');
		run('bash', [script, ...args]);
	}
} catch (err) {
	console.error('Dev script failed:', err);
	process.exitCode = 1;
}
