/* Run with PLAYWRIGHT_MODULE pointing to an installed Playwright module and
 * PELE_TEST_URL pointing to an HTTP server whose document root is vendor/.
 * Example: node --test Tests\JavaScript\Pele.test.js
 */
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || '@playwright/test');
const sBase = process.env.PELE_TEST_URL || 'http://127.0.0.1:5197';
const sCore = join(__dirname, '..', '..');
const sAssets = sBase + '/exface/core/Facades/AbstractAjaxFacade';
const oFixture = JSON.parse(execFileSync('php', [join(__dirname, 'Fixtures', 'Pele.php')], {encoding: 'utf8'}));

test('Core Pele documentation', {timeout: 120000}, async function (t) {
    const oBrowser = await chromium.launch();
    try {
        for (const sTemplate of ['template.html', 'templatePDF.html']) {
            await t.test(sTemplate + ' preserves custom colors and folded notes', async function () {
                const oPage = await oBrowser.newPage({viewport: {width: 1100, height: 900}});
                try {
                    const aErrors = [];
                    const aRequests = [];
                    oPage.on('pageerror', oError => aErrors.push(oError.message));
                    oPage.on('request', oRequest => aRequests.push(oRequest.url()));
                    const sHtml = readFileSync(join(sCore, 'Facades', 'DocsFacade', sTemplate), 'utf8')
                        .replaceAll('[#baseurl#]', sBase)
                        .replaceAll('[#title#]', 'Pele diagrams')
                        .replaceAll('[#breadcrumbs#]', '')
                        .replaceAll('[#content#]', oFixture.html);
                    await oPage.route('**/__pele_docs.html', oRoute => oRoute.fulfill({contentType: 'text/html', body: sHtml}));
                    await oPage.goto(sBase + '/__pele_docs.html');
                    assert.equal(await oPage.locator('.exf-diagram > svg.pele').count(), 4);
                    const oColors = await oPage.evaluate(function () {
                        function colors(sSelector) {
                            const oStyle = getComputedStyle(document.querySelector(sSelector));
                            return [oStyle.fill, oStyle.stroke];
                        }
                        return {
                            state: colors('.pele-state [data-id="Draft"] rect'),
                            chain: colors('[data-id="lb0"] rect'),
                            error: colors('[data-id="lbERR"] rect'),
                            normal: colors('[data-id="lbT"] rect'),
                            note: colors('.pele-sequence .pele-note > path'),
                            fold: colors('.pele-sequence .exf-diagram-note-fold'),
                            labels: document.querySelector('.pele-state').textContent,
                            ids: Array.from(document.querySelectorAll('svg.pele'), domSvg => domSvg.id),
                            repeated: exfMermaid.renderAll(document).length,
                            mermaid: typeof window.mermaid
                        };
                    });
                    assert.deepEqual(oColors.state, ['rgb(255, 238, 204)', 'rgb(255, 102, 0)']);
                    assert.deepEqual(oColors.chain, ['rgb(255, 99, 71)', 'rgb(255, 0, 0)']);
                    assert.deepEqual(oColors.error, oColors.chain);
                    assert.deepEqual(oColors.normal, ['rgb(241, 243, 244)', 'rgb(89, 100, 95)']);
                    assert.equal(oColors.note[0], 'rgb(255, 244, 204)');
                    assert.equal(oColors.fold[0], 'rgb(230, 207, 122)');
                    assert.match(oColors.labels, /Awaiting review/);
                    assert.match(oColors.labels, /UpdateData/);
                    assert.equal(new Set(oColors.ids).size, 4);
                    assert.equal(oColors.repeated, 0);
                    assert.equal(oColors.mermaid, 'undefined');
                    assert.equal(aRequests.some(sUrl => /\/js\/mermaid\/|\/axenox\/markian\/|\/kabachello\/codiware\//.test(sUrl)), false);
                    assert.deepEqual(aErrors, []);
                    const oDark = await oPage.evaluate(function () {
                        document.body.dataset.theme = 'dark';
                        return {
                            normal: getComputedStyle(document.querySelector('[data-id="lbT"] rect')).fill,
                            error: getComputedStyle(document.querySelector('[data-id="lbERR"] rect')).fill,
                            state: getComputedStyle(document.querySelector('.pele-state [data-id="Draft"] rect')).fill,
                            note: getComputedStyle(document.querySelector('.pele-sequence .pele-note > path')).fill
                        };
                    });
                    assert.equal(oDark.normal, 'rgb(43, 51, 48)');
                    assert.equal(oDark.error, 'rgb(255, 99, 71)');
                    assert.equal(oDark.state, 'rgb(255, 238, 204)');
                    assert.equal(oDark.note, 'rgb(73, 65, 35)');
                    if (sTemplate === 'template.html') {
                        const oBefore = await oPage.locator('.svg-pan-zoom_viewport').first().getAttribute('transform');
                        await oPage.locator('#svg-pan-zoom-zoom-in').first().click();
                        await oPage.waitForFunction(sBefore => document.querySelector('.svg-pan-zoom_viewport').getAttribute('transform') !== sBefore, oBefore);
                        const aControls = await oPage.locator('svg.pele').evaluateAll(function (aSvg) {
                            return aSvg.flatMap(function (domSvg) {
                                const oSvg = domSvg.getBoundingClientRect();
                                return Array.from(domSvg.querySelectorAll('.svg-pan-zoom-control'), function (domControl) {
                                    const oControl = domControl.getBoundingClientRect();
                                    return oControl.top >= oSvg.top - 1 && oControl.bottom <= oSvg.bottom + 1;
                                });
                            });
                        });
                        assert.ok(aControls.length > 0);
                        assert.ok(aControls.every(Boolean), 'Zoom controls must fit even short diagrams');
                    } else {
                        await oPage.emulateMedia({media: 'print'});
                        assert.equal(await oPage.locator('svg.pele').count(), 4);
                        assert.equal(await oPage.locator('.svg-pan-zoom-control').count(), 0);
                    }
                } finally {
                    await oPage.close();
                }
            });
        }

        await t.test('invalid diagrams retain literal source and can be retried', async function () {
            const oPage = await oBrowser.newPage();
            try {
                await oPage.goto(sBase);
                await oPage.setContent('<pre><code class="language-mermaid">not a diagram &lt;script&gt;bad()&lt;/script&gt;</code></pre>');
                await oPage.addScriptTag({url: sBase + '/npm-asset/pele/dist/pele.global.js'});
                await oPage.addScriptTag({url: sAssets + '/js/exfMermaid.js'});
                const sSource = await oPage.locator('code').textContent();
                await oPage.evaluate(() => exfMermaid.renderAll(document));
                assert.equal(await oPage.locator('code').textContent(), sSource);
                assert.equal(await oPage.locator('[role="alert"]').count(), 1);
                assert.equal(await oPage.locator('script:not([src])').count(), 0);
                await oPage.evaluate(function () {
                    document.querySelector('code').textContent = 'graph LR\n A-->B';
                    exfMermaid.renderAll(document);
                });
                assert.equal(await oPage.locator('svg.pele').count(), 1);
                assert.equal(await oPage.locator('[role="alert"]').count(), 0);
            } finally {
                await oPage.close();
            }
        });
    } finally {
        await oBrowser.close();
    }
});
