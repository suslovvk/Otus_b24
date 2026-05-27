<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
Loc::loadMessages(__FILE__);

Extension::load('ui.buttons');

$contactId = (int) $arResult['CONTACT_ID'];
$patientId = (int) $arResult['PATIENT_ID'];
$uid       = 'mxbal_' . $contactId;
?>
<link rel="stylesheet" href="/local/modules/matrix.integration/admin/options/assets/crm_tab.css">

<?php if ($patientId <= 0): ?>
<div class="matrix-no-patient">
    <?= Loc::getMessage('MATRIX_BALANCE_NO_PATIENT_LINKED') ?><br>
    Заполните поле <strong>UF_MATRIX_PATIENT_ID</strong> у этого контакта.
</div>
<?php else: ?>

<div id="<?= $uid ?>">

    <div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <button class="ui-btn ui-btn-primary ui-btn-sm" onclick="<?= $uid ?>mod.sync()"><?= Loc::getMessage('MATRIX_COMMON_BTN_SYNC') ?></button>
        <button class="ui-btn ui-btn-default ui-btn-sm" onclick="<?= $uid ?>mod.showReplenish()"><?= Loc::getMessage('MATRIX_BALANCE_BTN_REPLENISH') ?></button>
        <button class="ui-btn ui-btn-default ui-btn-sm" onclick="<?= $uid ?>mod.showCharge()"><?= Loc::getMessage('MATRIX_BALANCE_BTN_CHARGE_BONUS') ?></button>
        <span id="<?= $uid ?>-sync-status" style="font-size:12px;color:#888"></span>
    </div>

    <div class="matrix-balance-grid" id="<?= $uid ?>-cards">
        <div class="matrix-balance-card">
            <div class="label"><?= Loc::getMessage('MATRIX_BALANCE_LABEL_CASH') ?></div>
            <div class="value" id="<?= $uid ?>-cash">—</div>
            <div class="date"  id="<?= $uid ?>-cash-date"></div>
        </div>
        <div class="matrix-balance-card">
            <div class="label"><?= Loc::getMessage('MATRIX_BALANCE_LABEL_BONUS') ?></div>
            <div class="value" id="<?= $uid ?>-bonus">—</div>
        </div>
    </div>

    <h4 style="margin:0 0 10px"><?= Loc::getMessage('MATRIX_BALANCE_SECTION_BONUS_LOG') ?></h4>

</div>

<?php
$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID'                   => $arResult['BONUS_LOG_GRID_ID'],
        'COLUMNS'                   => $arResult['BONUS_LOG_HEADERS'],
        'ROWS'                      => $arResult['BONUS_LOG_ROWS'],
        'NAV_OBJECT'                => $arResult['BONUS_LOG_NAV'],
        'AJAX_MODE'                 => 'Y',
        'AJAX_ID'                   => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
        'AJAX_OPTION_JUMP'          => 'N',
        'AJAX_OPTION_HISTORY'       => 'N',
        'SHOW_ROW_CHECKBOXES'       => false,
        'SHOW_CHECK_ALL_CHECKBOXES' => false,
        'SHOW_ROW_ACTIONS_MENU'     => false,
        'SHOW_GRID_SETTINGS_MENU'   => true,
        'SHOW_NAVIGATION_PANEL'     => true,
        'SHOW_PAGINATION'           => true,
        'SHOW_SELECTED_COUNTER'     => false,
        'SHOW_TOTAL_COUNTER'        => false,
        'SHOW_PAGESIZE'             => true,
        'SHOW_ACTION_PANEL'         => false,
        'ALLOW_COLUMNS_SORT'        => true,
        'ALLOW_COLUMNS_RESIZE'      => true,
        'ALLOW_HORIZONTAL_SCROLL'   => true,
        'ALLOW_SORT'                => true,
        'ALLOW_PIN_HEADER'          => false,
    ]
);
?>

<script>
(function () {
    var uid         = <?= json_encode($uid) ?>;
    var contactId   = <?= $contactId ?>;
    var patientId   = <?= $patientId ?>;
    var ajaxUrl     = <?= json_encode($arResult['AJAX_URL']) ?>;
    var sessid      = <?= json_encode($arResult['SESSID']) ?>;
    var lazyloadUrl = <?= json_encode(\Bitrix\Main\Context::getCurrent()->getRequest()->getRequestUri()) ?>;
    var mxLang = <?= json_encode([
        'close'              => Loc::getMessage('MATRIX_COMMON_BTN_CLOSE'),
        'sumLabel'           => Loc::getMessage('MATRIX_BALANCE_FORM_SUM'),
        'commentLabel'       => Loc::getMessage('MATRIX_BALANCE_FORM_COMMENT'),
        'invalidSum'         => Loc::getMessage('MATRIX_BALANCE_ERROR_INVALID_SUM'),
        'replenishBtn'       => Loc::getMessage('MATRIX_BALANCE_BTN_REPLENISH_SUBMIT'),
        'sending'            => Loc::getMessage('MATRIX_COMMON_STATE_SENDING'),
        'replenished'        => Loc::getMessage('MATRIX_BALANCE_SUCCESS_REPLENISHED'),
        'connectionError'    => Loc::getMessage('MATRIX_COMMON_ERROR_CONNECTION'),
        'cancel'             => Loc::getMessage('MATRIX_COMMON_BTN_CANCEL'),
        'replenishTitle'     => Loc::getMessage('MATRIX_BALANCE_MODAL_REPLENISH_TITLE'),
        'bonusAmountLabel'   => Loc::getMessage('MATRIX_BALANCE_FORM_BONUS_AMOUNT'),
        'startDateLabel'     => Loc::getMessage('MATRIX_BALANCE_FORM_START_DATE'),
        'endDateLabel'       => Loc::getMessage('MATRIX_BALANCE_FORM_END_DATE'),
        'chargeBtn'          => Loc::getMessage('MATRIX_BALANCE_BTN_CHARGE_SUBMIT'),
        'invalidBonusSum'    => Loc::getMessage('MATRIX_BALANCE_ERROR_INVALID_BONUS_SUM'),
        'charged'            => Loc::getMessage('MATRIX_BALANCE_SUCCESS_CHARGED'),
        'chargeTitle'        => Loc::getMessage('MATRIX_BALANCE_MODAL_CHARGE_TITLE'),
        'syncing'            => Loc::getMessage('MATRIX_COMMON_STATE_SYNCING'),
        'updated'            => Loc::getMessage('MATRIX_COMMON_STATUS_UPDATED'),
    ]) ?>;

    function post(action, extra) {
        var body = Object.assign(
            {action: action, contact_id: contactId, patient_id: patientId, sessid: sessid},
            extra || {}
        );
        return fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: Object.keys(body).map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(body[k]);
            }).join('&')
        }).then(function (r) { return r.json(); });
    }

    function el(suffix) { return document.getElementById(uid + '-' + suffix); }

    /* ── Хелперы построения модалки ─────────────────────────────────────── */

    function createPopup(id, title, contentNode) {
        var existing = BX.Main.PopupManager.getPopupById(id);
        if (existing) { existing.destroy(); }
        return BX.Main.PopupManager.create({
            id:         id,
            title:      title,
            content:    contentNode,
            closeIcon:  true,
            overlay:    {opacity: 50},
            width:      620,
            autoHide:   false,
            closeByEsc: true,
        });
    }

    function makeField(labelText, inputEl) {
        var wrap = document.createElement('div');
        wrap.style.cssText = 'flex:1;min-width:0';
        var lbl = document.createElement('label');
        lbl.textContent = labelText;
        lbl.style.cssText = 'display:block;font-size:12px;color:#6d6d6d;margin-bottom:4px';
        inputEl.className = 'ui-ctl-element';
        inputEl.style.cssText = 'width:100%;box-sizing:border-box';
        wrap.appendChild(lbl);
        wrap.appendChild(inputEl);
        return wrap;
    }

    function makeInput(type, placeholder, attrs) {
        var inp = document.createElement('input');
        inp.type        = type;
        inp.placeholder = placeholder || '';
        Object.assign(inp, attrs || {});
        return inp;
    }

    function makeRow() {
        var row = document.createElement('div');
        row.style.cssText = 'display:flex;gap:12px;margin-bottom:12px';
        return row;
    }

    function makeFooter(buttons) {
        var foot = document.createElement('div');
        foot.style.cssText = 'display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:1px solid #e5e5e5';
        buttons.forEach(function (b) { foot.appendChild(b); });
        return foot;
    }

    function makeBtn(text, cls, onClick) {
        var btn = document.createElement('button');
        btn.textContent = text;
        btn.className   = 'ui-btn ui-btn-sm ' + cls;
        btn.style.margin = '0';
        btn.onclick = onClick;
        return btn;
    }

    function showPopupResult(popupId, success, message) {
        var popup = BX.Main.PopupManager.getPopupById(popupId);
        if (!popup) { return; }

        var wrap = document.createElement('div');
        wrap.style.cssText = 'padding:24px 0;text-align:center';

        var icon = document.createElement('div');
        icon.style.cssText = 'font-size:36px;margin-bottom:12px;color:' + (success ? '#2e7d32' : '#c62828');
        icon.textContent = success ? '✓' : '✗';

        var msg = document.createElement('div');
        msg.style.cssText = 'font-size:14px;margin-bottom:20px;color:' + (success ? '#2e7d32' : '#c62828');
        msg.textContent = message;

        wrap.appendChild(icon);
        wrap.appendChild(msg);
        wrap.appendChild(makeFooter([
            makeBtn(mxLang.close, 'ui-btn-default', function () { popup.close(); })
        ]));

        popup.setContent(wrap);
    }

    /* ── Модалка: пополнение баланса ─────────────────────────────────────── */

    var mod = {

        showReplenish: function () {
            var popupId    = uid + '-replenish-popup';
            var sumInp     = makeInput('number', '1000', {min: '0.01', step: '0.01'});
            var commentInp = makeInput('text', 'Причина пополнения');

            var row = makeRow();
            row.appendChild(makeField(mxLang.sumLabel, sumInp));
            row.appendChild(makeField(mxLang.commentLabel, commentInp));

            var errMsg = document.createElement('div');
            errMsg.style.cssText = 'min-height:18px;font-size:12px;color:#c62828;margin-bottom:4px';

            var submitBtn, cancelBtn;

            submitBtn = makeBtn(mxLang.replenishBtn, 'ui-btn-success', function () {
                if (!sumInp.value || parseFloat(sumInp.value) <= 0) {
                    errMsg.textContent = mxLang.invalidSum;
                    return;
                }
                errMsg.textContent    = '';
                submitBtn.disabled    = true;
                cancelBtn.disabled    = true;
                submitBtn.textContent = mxLang.sending;
                post('replenish_balance', {sum: sumInp.value, comment: commentInp.value})
                    .then(function (d) {
                        showPopupResult(popupId, d.success, d.success ? mxLang.replenished : (d.message || 'Ошибка'));
                        if (d.success) { mod.refreshBalance(); }
                    })
                    .catch(function () { showPopupResult(popupId, false, mxLang.connectionError); });
            });

            cancelBtn = makeBtn(mxLang.cancel, 'ui-btn-default', function () {
                BX.Main.PopupManager.getPopupById(popupId).close();
            });

            var wrap = document.createElement('div');
            wrap.appendChild(row);
            wrap.appendChild(errMsg);
            wrap.appendChild(makeFooter([submitBtn, cancelBtn]));

            createPopup(popupId, mxLang.replenishTitle, wrap).show();
        },

        /* ── Модалка: начисление бонусов ─────────────────────────────────── */

        showCharge: function () {
            var popupId = uid + '-charge-popup';

            var today   = new Date().toISOString().slice(0, 10);
            var plus30  = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);

            var amountInp  = makeInput('number', '100', {min: '1', step: '1'});
            var commentInp = makeInput('text', 'Причина начисления');
            var actInp     = makeInput('date', '', {value: today});
            var deactInp   = makeInput('date', '', {value: plus30});

            var row1 = makeRow();
            row1.appendChild(makeField(mxLang.bonusAmountLabel, amountInp));
            row1.appendChild(makeField(mxLang.commentLabel, commentInp));

            var row2 = makeRow();
            row2.appendChild(makeField(mxLang.startDateLabel, actInp));
            row2.appendChild(makeField(mxLang.endDateLabel, deactInp));

            var errMsg = document.createElement('div');
            errMsg.style.cssText = 'min-height:18px;font-size:12px;color:#c62828;margin-bottom:4px';

            var submitBtn, cancelBtn;

            submitBtn = makeBtn(mxLang.chargeBtn, 'ui-btn-success', function () {
                if (!amountInp.value || parseInt(amountInp.value) <= 0) {
                    errMsg.textContent = mxLang.invalidBonusSum;
                    return;
                }
                errMsg.textContent    = '';
                submitBtn.disabled    = true;
                cancelBtn.disabled    = true;
                submitBtn.textContent = mxLang.sending;
                post('charge_bonus', {
                    amount:         amountInp.value,
                    note:           commentInp.value,
                    activated_at:   actInp.value,
                    deactivated_at: deactInp.value,
                })
                    .then(function (d) {
                        showPopupResult(popupId, d.success, d.success ? mxLang.charged : (d.message || 'Ошибка'));
                        if (d.success) { mod.refreshBonus(); }
                    })
                    .catch(function () { showPopupResult(popupId, false, mxLang.connectionError); });
            });

            cancelBtn = makeBtn(mxLang.cancel, 'ui-btn-default', function () {
                BX.Main.PopupManager.getPopupById(popupId).close();
            });

            var wrap = document.createElement('div');
            wrap.appendChild(row1);
            wrap.appendChild(row2);
            wrap.appendChild(errMsg);
            wrap.appendChild(makeFooter([submitBtn, cancelBtn]));

            createPopup(popupId, mxLang.chargeTitle, wrap).show();
        },

        /* ── Данные ──────────────────────────────────────────────────────── */

        loadBalance: function () {
            post('get_balance').then(function (d) {
                if (!d.success) { return; }
                el('cash').textContent      = d.balance !== null ? d.balance + ' ₽' : '—';
                el('cash-date').textContent = d.updated_at || '';
                el('bonus').textContent     = d.bonus_balance !== null ? d.bonus_balance + ' б.' : '—';
            });
        },

        reloadGrid: function () {
            var gridId = <?= json_encode($arResult['BONUS_LOG_GRID_ID']) ?>;
            if (window.BX && BX.Main && BX.Main.gridManager) {
                var wrapper = BX.Main.gridManager.getById(gridId);
                var g = wrapper ? wrapper.instance : null;
                if (g) {
                    if (g.baseUrl && g.baseUrl.indexOf('ENTITY_ID=') === -1) {
                        g.baseUrl = lazyloadUrl;
                    }
                    if (typeof g.reload === 'function') {
                        g.reload();
                        return;
                    }
                }
            }
            /* Fallback: перезагружаем страницу CRM-вкладки через lazyload */
            var container = document.getElementById(uid);
            if (container) {
                var root = container.closest('[data-component]') || container.parentNode;
                if (root) {
                    var params = 'PARAMS%5Bparams%5D%5BENTITY_ID%5D=' + contactId
                        + '&PARAMS%5Bparams%5D%5BENTITY_TYPE_ID%5D=3'
                        + '&sessid=' + encodeURIComponent(sessid);
                    fetch('/local/components/matrix/crm.patient.balance/lazyload.ajax.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: params,
                    }).then(function (r) { return r.text(); }).then(function (html) {
                        BX.html(root, html);
                    });
                }
            }
        },

        sync: function () {
            var st = el('sync-status');
            st.textContent = mxLang.syncing;
            post('sync_patient').then(function (d) {
                st.textContent = d.success ? mxLang.updated : ('Ошибка: ' + d.message);
                if (d.success) {
                    mod.loadBalance();
                    mod.reloadGrid();
                }
            });
        },

        refreshBalance: function () {
            post('sync_balance').then(function (d) {
                if (d.success) { mod.loadBalance(); }
            });
        },

        refreshBonus: function () {
            post('sync_bonus').then(function (d) {
                if (d.success) { mod.reloadGrid(); }
            });
        },
    };

    window[uid + 'mod'] = mod;
    mod.loadBalance();

    // Patch grid.baseUrl after grid initializes so column-settings reload
    // uses lazyload URL (with ENTITY_ID) instead of window.location (CRM page).
    var bonusGridId = <?= json_encode($arResult['BONUS_LOG_GRID_ID']) ?>;
    function patchBonusGridBaseUrl() {
        if (!window.BX || !BX.Main || !BX.Main.gridManager) { return; }
        var wrapper = BX.Main.gridManager.getById(bonusGridId);
        var g = wrapper ? wrapper.instance : null;
        if (g && g.baseUrl && g.baseUrl.indexOf('ENTITY_ID=') === -1) {
            g.baseUrl = lazyloadUrl;
        }
    }
    BX.ready(patchBonusGridBaseUrl);
    setTimeout(patchBonusGridBaseUrl, 500);
})();
</script>

<?php endif; ?>
