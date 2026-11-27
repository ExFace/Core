// Run with node --test Tests\JavaScript\RangeFormatter.test.js (requires PHP and the Composer vendor tree).
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const moment = require('../../../../npm-asset/moment/moment.js');
const {createContext, runInContext} = require('node:vm');

const expressions = JSON.parse(execFileSync('php', ['-n', join(__dirname, 'Fixtures', 'RangeFormatter.php')], {encoding: 'utf8'}));
const ctx = createContext({moment});
runInContext(readFileSync(join(__dirname, '../../Facades/AbstractAjaxFacade/js/exfTools.js'), 'utf8'), ctx);
moment.now = () => new Date(2026, 9, 26, 12).getTime();

function evaluate(name, input) {
    ctx.input = input;
    return runInContext('(' + expressions[name] + ')', ctx);
}

test('formats and parses each endpoint while keeping one BETWEEN value', () => {
    assert.equal(evaluate('format', '2026-10-26..2026-10-28'), '26.10.2026 .. 28.10.2026');
    assert.equal(evaluate('parse', '26.10.2026 .. 28.10.2026'), '2026-10-26..2026-10-28');
    assert.equal(evaluate('plain', ' a .. z '), 'a..z');
    const {comparator, value} = evaluate('filter', '3w..5w');
    assert.equal(comparator, '..');
    assert.equal(value, '2026-11-16..2026-11-30');
    assert.equal(evaluate('validate', '3w..5w'), true);
});

test('rejects invalid and open widget ranges without changing ordinary date filters', () => {
    for (const invalid of ['3w..', '..5w', '3w..4w..5w', '2026-02-30..2026-03-01',
        '2026-10-26 99:00..2026-10-28', '26.10.2026 24:00:00..28.10.2026']) {
        assert.equal(evaluate('validate', invalid), false);
        assert.equal(evaluate('parse', invalid), null);
        assert.throws(() => evaluate('filter', invalid), /two valid endpoints/);
    }
    for (const empty of ['', null, undefined, 'NULL', 'null']) {
        assert.equal(evaluate('validate', empty), true);
        assert.equal(evaluate('filter', empty).value, empty);
    }
    assert.equal(evaluate('ordinaryFilter', '26.10.2026..').value, '2026-10-26..');
});

test('picker ranges preserve exact timestamps and serialize day selections inclusively', () => {
    const {exfTools} = ctx;
    const {date} = exfTools;
    const exact = '2026-10-26 13:15:27..2026-10-28 16:17:24';
    const restored = date.parseRange(exact, 'dd.MM.yyyy', {dateTime: true});
    assert.equal(restored.hasTime, true);
    assert.equal(date.serializeRange(restored.dates, true, restored.hasTime), exact);
    assert.equal(evaluate('timestampFilter', exact).value, exact);
    assert.equal(evaluate('timestampFilter', '26.10.2026 13:15:27..28.10.2026 16:17:24').value, exact);
    assert.equal(date.parseRange('2026-10-26 24:00:00..2026-10-28 16:17:24', 'dd.MM.yyyy', {dateTime: true}), null);

    const days = date.parseRange('28.10.2026..26.10.2026', 'dd.MM.yyyy', {dateTime: true});
    assert.equal(days.hasTime, false);
    const wholeDays = '2026-10-26 00:00:00..2026-10-28 23:59:59';
    assert.equal(date.serializeRange(days.dates, true), wholeDays);
    assert.equal(evaluate('timestampFilter', wholeDays).value, wholeDays);
    assert.equal(date.parseRange('26.10.2026', 'dd.MM.yyyy'), null);
    const singleDay = date.parseRange('26.10.2026', 'dd.MM.yyyy', {allowSingle: true});
    assert.equal(date.serializeRange(singleDay.dates, false), '2026-10-26..2026-10-26');
});

test('restoring ISO and mixed bounds preserves exactly the specified clock times', () => {
    const {date} = ctx.exfTools;
    for (const [input, expected] of [
        ['2026-11-01T10:00:00..2026-11-02T11:00:00', '2026-11-01 10:00:00..2026-11-02 11:00:00'],
        ['2026-11-02..2026-11-01T10:30', '2026-11-01 10:30:00..2026-11-02 23:59:59'],
        ['2026-11-01..01.11.2026 10:00', '2026-11-01 00:00:00..2026-11-01 10:00:00'],
        ['01.11.2026 10:00..2026-11-01', '2026-11-01 10:00:00..2026-11-01 23:59:59']
    ]) {
        const {dates, hasTime} = date.parseRange(input, 'dd.MM.yyyy', {dateTime: true});
        assert.equal(date.serializeRange(dates, true, hasTime), expected);
    }
});

test('custom presets require two completely parsed bounds', () => {
    const {date} = ctx.exfTools;
    const resolve = from => date.resolveRangePreset({range: 'custom', from, to: '0'}, 'dd.MM.yyyy', true);
    for (const invalid of ['2026-11-02oops', '1.11.2026 10:00 extra', '2026-11-02T24:00']) {
        assert.equal(resolve(invalid), null);
    }
    assert.equal(date.serializeRange(resolve('1.10'), false), '2026-10-01..2026-10-26');
    assert.equal(date.serializeRange(resolve('-3w'), false), '2026-10-05..2026-10-26');
});
