<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

$contactId = (int) $arResult['CONTACT_ID'];
$patientId = (int) $arResult['PATIENT_ID'];
$uid       = 'mxrcpt_' . $contactId;
?>
<link rel="stylesheet" href="/local/modules/matrix.integration/admin/options/assets/crm_tab.css">

<?php if ($patientId <= 0): ?>
<div class="matrix-no-patient">
    <?= Loc::getMessage('MATRIX_RECEIPTS_NO_PATIENT_LINKED') ?><br>
    Заполните поле <strong>UF_MATRIX_PATIENT_ID</strong> у этого контакта.
</div>
<?php else: ?>

<div id="<?= $uid ?>">

    <div style="margin-bottom:12px;display:flex;align-items:center;gap:10px">
        <button class="matrix-btn matrix-btn-primary" onclick="<?= $uid ?>mod.sync()"><?= Loc::getMessage('MATRIX_COMMON_BTN_SYNC') ?></button>
        <span id="<?= $uid ?>-sync-status" style="font-size:12px;color:#888"></span>
    </div>

    <div class="matrix-loading" id="<?= $uid ?>-loading"><?= Loc::getMessage('MATRIX_COMMON_STATE_LOADING') ?></div>

    <table class="matrix-table" id="<?= $uid ?>-table" style="display:none">
        <thead>
            <tr>
                <th><?= Loc::getMessage('MATRIX_RECEIPTS_COL_DATE') ?></th>
                <th><?= Loc::getMessage('MATRIX_RECEIPTS_COL_AMOUNT') ?></th>
                <th><?= Loc::getMessage('MATRIX_RECEIPTS_COL_ITEMS') ?></th>
                <th><?= Loc::getMessage('MATRIX_RECEIPTS_COL_DEAL') ?></th>
            </tr>
        </thead>
        <tbody id="<?= $uid ?>-body"></tbody>
    </table>

    <div class="matrix-empty" id="<?= $uid ?>-empty" style="display:none"><?= Loc::getMessage('MATRIX_RECEIPTS_EMPTY') ?></div>
    <div class="matrix-pagination" id="<?= $uid ?>-pagination"></div>

</div>

<script>
(function () {
    var uid       = <?= json_encode($uid) ?>;
    var contactId = <?= $contactId ?>;
    var patientId = <?= $patientId ?>;
    var ajaxUrl   = <?= json_encode($arResult['AJAX_URL']) ?>;
    var sessid    = <?= json_encode($arResult['SESSID']) ?>;
    var page      = 1;
    var pageSize  = 15;
    var total     = 0;
    var mxLang = <?= json_encode([
        'syncing' => Loc::getMessage('MATRIX_COMMON_STATE_SYNCING'),
        'updated' => Loc::getMessage('MATRIX_COMMON_STATUS_UPDATED'),
    ]) ?>;

    function post(action, extra) {
        var body = Object.assign({action: action, contact_id: contactId, patient_id: patientId, sessid: sessid}, extra || {});
        return fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: Object.keys(body).map(function(k) { return encodeURIComponent(k) + '=' + encodeURIComponent(body[k]); }).join('&')
        }).then(function(r) { return r.json(); });
    }

    function el(s) { return document.getElementById(uid + '-' + s); }

    var mod = {
        load: function (p) {
            page = p || page;
            el('loading').style.display = '';
            el('table').style.display   = 'none';
            el('empty').style.display   = 'none';

            post('get_receipts', {page: page}).then(function (d) {
                el('loading').style.display = 'none';
                if (!d.success || !d.items) return;
                total = d.total || 0;
                if (!d.items.length) { el('empty').style.display = ''; return; }

                var tbody = el('body');
                tbody.innerHTML = '';
                d.items.forEach(function (r) {
                    var deal = r.bitrix_deal_id > 0
                        ? '<a href="/crm/deal/show/' + r.bitrix_deal_id + '/" target="_blank">#' + r.bitrix_deal_id + '</a>'
                        : '—';
                    var itemsText = '';
                    if (Array.isArray(r.items) && r.items.length) {
                        itemsText = r.items.map(function (it) {
                            var n = it.name || it.procedure_name || '';
                            var qty = it.amount || it.quantity || 1;
                            return n + (qty > 1 ? ' ×' + qty : '');
                        }).join(', ');
                    }
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + (r.created_at || '—') + '</td>' +
                        '<td>' + (r.total_amount ? r.total_amount + ' ₽' : '—') + '</td>' +
                        '<td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + itemsText + '">' + (itemsText || '—') + '</td>' +
                        '<td>' + deal + '</td>';
                    tbody.appendChild(tr);
                });
                el('table').style.display = '';
                mod.renderPagination();
            });
        },

        sync: function () {
            var st = el('sync-status');
            st.textContent = mxLang.syncing;
            post('sync_patient').then(function (d) {
                st.textContent = d.success ? mxLang.updated : ('Ошибка: ' + d.message);
                if (d.success) mod.load(1);
            });
        },

        renderPagination: function () {
            var pages = Math.ceil(total / pageSize);
            var pag   = el('pagination');
            pag.innerHTML = '';
            if (pages <= 1) return;
            for (var i = 1; i <= pages; i++) {
                var btn = document.createElement('button');
                btn.textContent = i;
                btn.disabled    = (i === page);
                (function (p) { btn.onclick = function () { mod.load(p); }; })(i);
                pag.appendChild(btn);
            }
        }
    };

    window[uid + 'mod'] = mod;
    mod.load(1);
})();
</script>

<?php endif; ?>
