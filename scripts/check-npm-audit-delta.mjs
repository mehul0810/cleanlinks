import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const severity = { low: 1, moderate: 2, high: 3, critical: 4 };

export function findings(report) {
	if (!report || report.error || !report.metadata?.vulnerabilities || !report.vulnerabilities || typeof report.vulnerabilities !== 'object') {
		throw new Error('npm audit did not return a complete vulnerability report');
	}
	const reportedTotal = report.metadata.vulnerabilities.total;
	if (!Number.isInteger(reportedTotal) || reportedTotal !== Object.keys(report.vulnerabilities).length) {
		throw new Error('npm audit vulnerability totals do not match the findings');
	}

	const result = new Map();
	for (const [name, vulnerability] of Object.entries(report.vulnerabilities)) {
		if (!severity[vulnerability.severity] || !Array.isArray(vulnerability.via) || !vulnerability.via.length) {
			throw new Error(`Invalid npm audit finding for ${name}`);
		}
		for (const via of vulnerability.via) {
			const advisory = typeof via === 'string' ? via : via.source ?? via.url;
			if (!advisory) {
				throw new Error(`Missing advisory identity for ${name}`);
			}
			const advisorySeverity = typeof via === 'string' ? vulnerability.severity : via.severity;
			if (!severity[advisorySeverity]) {
				throw new Error(`Invalid advisory severity for ${name}`);
			}
			const key = `${name}:${advisory}`;
			result.set(key, Math.max(result.get(key) ?? 0, severity[advisorySeverity]));
		}
	}
	return result;
}

export function newHighFindings(current, base) {
	return [...current].filter(([key, level]) => level >= severity.high && level > (base.get(key) ?? 0));
}

export function comparisonRequired(eventName, baseSha) {
	if (eventName === 'pull_request' && !baseSha) {
		throw new Error('Pull request base SHA is missing; dependency delta cannot be checked');
	}
	return Boolean(baseSha);
}

function audit(directory) {
	const run = spawnSync('npm', ['audit', '--json', '--package-lock-only'], {
		cwd: directory,
		encoding: 'utf8',
		maxBuffer: 20 * 1024 * 1024,
	});
	if (run.error || (run.status !== 0 && run.status !== 1)) {
		throw new Error(`npm audit failed: ${run.error?.message ?? run.stderr}`);
	}
	let report;
	try {
		report = JSON.parse(run.stdout);
	} catch {
		throw new Error('npm audit did not return valid JSON');
	}
	return { report, findings: findings(report) };
}

function main() {
	const current = audit(process.cwd());
	console.log(`Current npm lockfile: ${JSON.stringify(current.report.metadata.vulnerabilities)}`);

	if (!comparisonRequired(process.env.GITHUB_EVENT_NAME, process.env.BASE_SHA)) {
		console.log('Baseline report only: no pull request base SHA was provided.');
		return;
	}
	if (!/^[0-9a-f]{40}$/.test(process.env.BASE_SHA)) {
		throw new Error('Invalid pull request base SHA');
	}

	const directory = mkdtempSync(join(tmpdir(), 'cleanlinks-audit-base-'));
	try {
		for (const file of ['package.json', 'package-lock.json']) {
			const contents = execFileSync('git', ['show', `${process.env.BASE_SHA}:${file}`]);
			writeFileSync(join(directory, file), contents);
		}
		const base = audit(directory);
		console.log(`Base npm lockfile: ${JSON.stringify(base.report.metadata.vulnerabilities)}`);
		const delta = newHighFindings(current.findings, base.findings);
		if (delta.length) {
			for (const [key, level] of delta) {
				console.error(`New or worsened ${Object.keys(severity)[level - 1]} finding: ${key}`);
			}
			throw new Error(`${delta.length} new or worsened high/critical npm findings`);
		}
		console.log('No new or worsened high/critical npm findings. Existing findings remain visible above.');
	} finally {
		rmSync(directory, { recursive: true, force: true });
	}
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
	try {
		main();
	} catch (error) {
		console.error(error.message);
		process.exitCode = 1;
	}
}
