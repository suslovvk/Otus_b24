<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;

Loc::loadMessages(__FILE__);
Extension::load('ui.buttons');

$isTabMode = (bool) $arResult['IS_TAB_MODE'];
$contactId = (int) $arResult['CONTACT_ID'];
$patientId = (int) $arResult['PATIENT_ID'];
$uid       = $isTabMode ? 'mxturn_' . $contactId : 'mxturn_all';
?>
<link rel="stylesheet" href="/local/modules/matrix.integration/admin/options/assets/crm_tab.css">

<?php if ($isTabMode && $patientId <= 0): ?>
<div class="matrix-no-patient">
    <?= Loc::getMessage('MATRIX_TURNOUTS_NO_PATIENT_LINKED') ?><br>
    Заполните поле <strong>UF_MATRIX_PATIENT_ID</strong> у этого контакта.
</div>
<?php else: ?>

<?php if ($isTabMode): ?>
<div id="<?= $uid ?>">
    <div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <button class="ui-btn ui-btn-primary ui-btn-sm" onclick="<?= $uid ?>mod.sync()"><?= Loc::getMessage('MATRIX_COMMON_BTN_SYNC') ?></button>
        <span id="<?= $uid ?>-sync-status" style="font-size:12px;color:#888"></span>
    </div>
    <h4 style="margin:0 0 10px"><?= Loc::getMessage('MATRIX_TURNOUTS_SECTION_TITLE') ?></h4>
</div>
<?php endif; ?>

<?php
$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID'                   => $arResult['GRID_ID'],
        'COLUMNS'                   => $arResult['HEADERS'],
        'ROWS'                      => $arResult['ROWS'],
        'NAV_OBJECT'                => $arResult['NAV'],
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
        'FILTER_ID'                 => $arResult['FILTER_ID'] ?? '',
    ]
);
?>

<script>
(function () {
    var mxTurnAjaxUrl = <?= json_encode($arResult['AJAX_URL']) ?>;
    var mxTurnSessid  = <?= json_encode($arResult['SESSID']) ?>;
    var mxLang = <?= json_encode([
        'createActivity'      => Loc::getMessage('MATRIX_TURNOUTS_BTN_CREATE_ACTIVITY'),
        'errorCreateActivity' => Loc::getMessage('MATRIX_TURNOUTS_ERROR_CREATE_ACTIVITY'),
        'syncing'             => Loc::getMessage('MATRIX_COMMON_STATE_SYNCING'),
        'updated'             => Loc::getMessage('MATRIX_COMMON_STATUS_UPDATED'),
    ]) ?>;

    /* ── Кнопка «Создать дело»: capture-фаза, как у records ─────────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.mx-create-activity');
        if (!btn) { return; }
        e.stopPropagation();
        e.preventDefault();

        var turnoutId = btn.getAttribute('data-turnout-id');
        btn.disabled    = true;
        btn.textContent = '...';

        fetch(mxTurnAjaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=create_turnout_activity'
                + '&turnout_id=' + encodeURIComponent(turnoutId)
                + '&sessid='     + encodeURIComponent(mxTurnSessid),
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d.success) {
                var link = document.createElement('a');
                link.href        = '/crm/activity/' + d.activity_id + '/';
                link.target      = '_blank';
                link.textContent = '#' + d.activity_id;
                btn.replaceWith(link);
            } else {
                btn.disabled    = false;
                btn.textContent = mxLang.createActivity;
                alert(d.message || mxLang.errorCreateActivity);
            }
        }).catch(function () {
            btn.disabled    = false;
            btn.textContent = mxLang.createActivity;
        });
    }, true);

<?php if ($isTabMode): ?>
    /* ── Синхронизация (только в режиме таба) ────────────────────────────── */
    var uid         = <?= json_encode($uid) ?>;
    var contactId   = <?= $contactId ?>;
    var patientId   = <?= $patientId ?>;
    var gridId      = <?= json_encode($arResult['GRID_ID']) ?>;
    var lazyloadUrl = <?= json_encode(\Bitrix\Main\Context::getCurrent()->getRequest()->getRequestUri()) ?>;

    function post(action, extra) {
        var body = Object.assign(
            {action: action, contact_id: contactId, patient_id: patientId, sessid: mxTurnSessid},
            extra || {}
        );
        return fetch(mxTurnAjaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: Object.keys(body).map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(body[k]);
            }).join('&')
        }).then(function (r) { return r.json(); });
    }

    var mod = {
        sync: function () {
            var st = document.getElementById(uid + '-sync-status');
            st.textContent = mxLang.syncing;
            post('sync_patient').then(function (d) {
                st.textContent = d.success ? mxLang.updated : ('Ошибка: ' + d.message);
                if (d.success) {
                    var wrapper = BX.Main.gridManager.getById(gridId);
                    var g = wrapper ? wrapper.instance : null;
                    if (g && typeof g.reload === 'function') { g.reload(); }
                }
            });
        },
    };

    window[uid + 'mod'] = mod;

    function patchGridBaseUrl() {
        if (!window.BX || !BX.Main || !BX.Main.gridManager) { return; }
        var wrapper = BX.Main.gridManager.getById(gridId);
        var g = wrapper ? wrapper.instance : null;
        if (g && g.baseUrl && g.baseUrl.indexOf('ENTITY_ID=') === -1) {
            g.baseUrl = lazyloadUrl;
        }
    }
    BX.ready(patchGridBaseUrl);
    setTimeout(patchGridBaseUrl, 500);
<?php endif; ?>
})();
</script>

<?php endif; ?>
