import assert from 'node:assert/strict';
import { test } from 'node:test';
import { comparisonRequired, findings, newHighFindings } from './check-npm-audit-delta.mjs';

function report(vulnerabilities) {
	return { metadata: { vulnerabilities: { total: Object.keys(vulnerabilities).length } }, vulnerabilities };
}

test('unchanged inherited advisory does not fail the delta gate', () => {
	const baseline = findings(report({ packageA: { severity: 'high', via: [{ source: 123, severity: 'high' }] } }));
	assert.deepEqual(newHighFindings(baseline, baseline), []);
});

test('new and worsened high findings fail the delta gate', () => {
	const baseline = findings(report({ packageA: { severity: 'moderate', via: [{ source: 123, severity: 'moderate' }] } }));
	const current = findings(report({
		packageA: { severity: 'high', via: [{ source: 123, severity: 'high' }] },
		packageB: { severity: 'critical', via: ['transitive'] },
	}));
	assert.equal(newHighFindings(current, baseline).length, 2);
});

test('high to critical is a worsening finding', () => {
	const baseline = findings(report({ packageA: { severity: 'high', via: [{ source: 123, severity: 'high' }] } }));
	const current = findings(report({ packageA: { severity: 'critical', via: [{ source: 123, severity: 'critical' }] } }));
	assert.equal(newHighFindings(current, baseline).length, 1);
});

test('new moderate findings remain visible but do not fail the high-risk gate', () => {
	const current = findings(report({ packageA: { severity: 'moderate', via: [{ source: 456, severity: 'moderate' }] } }));
	assert.deepEqual(newHighFindings(current, new Map()), []);
});

test('pull requests require a base, while other events report the baseline', () => {
	assert.throws(() => comparisonRequired('pull_request', ''));
	assert.equal(comparisonRequired('push', ''), false);
	assert.equal(comparisonRequired('schedule', ''), false);
	assert.equal(comparisonRequired('pull_request', 'a'.repeat(40)), true);
});

test('missing or malformed audit reports fail closed', () => {
	assert.throws(() => findings({ error: { code: 'ENOTFOUND' } }));
	assert.throws(() => findings(report({ packageA: { severity: 'high', via: [{}] } })));
	assert.throws(() => findings(report({ packageA: { severity: 'high', via: [] } })));
	assert.throws(() => findings({ metadata: { vulnerabilities: { total: 1 } }, vulnerabilities: {} }));
});
