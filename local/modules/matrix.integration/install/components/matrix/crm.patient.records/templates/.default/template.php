<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
Loc::loadMessages(__FILE__);

Extension::load(['ui.buttons', 'ui.forms', 'ui.icons', 'ui.system.chip']);

$contactId = (int) $arResult['CONTACT_ID'];
$patientId = (int) $arResult['PATIENT_ID'];
$uid       = 'mxrec_' . $contactId;
$bk        = $uid . '-bk';   // префикс элементов формы записи
?>
<link rel="stylesheet" href="/local/modules/matrix.integration/admin/options/assets/crm_tab.css">
<style>
.mx-bk-label { font-size:12px; color:#6c737a; font-weight:600; margin-bottom:4px; text-transform:uppercase; letter-spacing:.4px; }
.mx-bk-field { margin-bottom:14px; }
.mx-bk-period-nav { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
.mx-bk-period-nav .ui-btn { min-width:36px; }
.mx-bk-period-title { font-weight:600; font-size:13px; min-width:200px; }
.mx-bk-day-block { margin-bottom:12px; }
.mx-bk-day-label { font-size:12px; color:#555; font-weight:600; margin-bottom:6px; }
.mx-bk-slot-row { display:flex; flex-wrap:wrap; gap:6px; }
.mx-bk-dropdown-wrap { position:relative; }
.mx-bk-dd-list { display:none; position:fixed; background:#fff; border:1px solid #d0d7de; border-top:none; border-radius:0 0 4px 4px; max-height:220px; overflow-y:auto; z-index:9999; box-shadow:0 4px 10px rgba(0,0,0,.12); }
.mx-bk-dd-list.open { display:block; }
.mx-bk-dd-item { padding:8px 12px; cursor:pointer; font-size:13px; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mx-bk-dd-item:hover,.mx-bk-dd-item.mx-bk-active { background:#f0f5fa; color:#1a6bae; }
.mx-bk-dd-empty { padding:8px 12px; font-size:13px; color:#aaa; }
.mx-bk-step-disabled .ui-ctl { opacity:0.35; pointer-events:none; }
</style>

<?php if ($patientId <= 0): ?>
<div class="matrix-no-patient">
    <?= Loc::getMessage('MATRIX_RECORDS_NO_PATIENT_LINKED') ?><br>
    Заполните поле <strong>UF_MATRIX_PATIENT_ID</strong> у этого контакта.
</div>
<?php else: ?>

<div id="<?= $uid ?>">

    <div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <button class="ui-btn ui-btn-success ui-btn-sm" id="<?= $uid ?>-open-booking"><?= Loc::getMessage('MATRIX_RECORDS_BTN_CREATE') ?></button>
        <button class="ui-btn ui-btn-primary ui-btn-sm" onclick="<?= $uid ?>mod.sync()"><?= Loc::getMessage('MATRIX_COMMON_BTN_SYNC') ?></button>
        <span id="<?= $uid ?>-sync-status" style="font-size:12px;color:#888"></span>
        <span id="<?= $uid ?>-book-status" style="font-size:12px"></span>
    </div>

    <h4 style="margin:0 0 10px"><?= Loc::getMessage('MATRIX_RECORDS_SECTION_HISTORY') ?></h4>

</div>

<?php
$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID'                   => $arResult['RECORDS_GRID_ID'],
        'COLUMNS'                   => $arResult['RECORDS_HEADERS'],
        'ROWS'                      => $arResult['RECORDS_ROWS'],
        'NAV_OBJECT'                => $arResult['RECORDS_NAV'],
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

<!-- Скрытый контент попапа записи на приём -->
<div id="<?= $bk ?>-inner" style="display:none; padding:20px; box-sizing:border-box;">

    <!-- Врач + Специальность + Процедура в одну строку -->
    <div style="display:flex; gap:12px; margin-bottom:14px; align-items:flex-end;">

        <div style="flex:1; min-width:0;">
            <div class="mx-bk-label"><?= Loc::getMessage('MATRIX_RECORDS_FORM_DOCTOR') ?></div>
            <div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown ui-ctl-w100 mx-bk-dropdown-wrap">
                <div class="ui-ctl-after ui-ctl-icon-angle"></div>
                <input type="text" class="ui-ctl-element" id="<?= $bk ?>-doctor-input" placeholder="<?= Loc::getMessage('MATRIX_COMMON_DROPDOWN_LOADING') ?>" autocomplete="on" readonly>
                <div class="mx-bk-dd-list" id="<?= $bk ?>-doctor-list"></div>
            </div>
        </div>

        <div id="<?= $bk ?>-step-specialty" class="mx-bk-step-disabled" style="flex:1; min-width:0;">
            <div class="mx-bk-label"><?= Loc::getMessage('MATRIX_RECORDS_FORM_SPECIALTY') ?></div>
            <div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown ui-ctl-w100 mx-bk-dropdown-wrap">
                <div class="ui-ctl-after ui-ctl-icon-angle"></div>
                <input type="text" class="ui-ctl-element" id="<?= $bk ?>-specialty-input" placeholder="<?= Loc::getMessage('MATRIX_COMMON_DROPDOWN_SELECT') ?>" autocomplete="off" readonly>
                <div class="mx-bk-dd-list" id="<?= $bk ?>-specialty-list"></div>
            </div>
        </div>

        <div id="<?= $bk ?>-step-procedure" class="mx-bk-step-disabled" style="flex:1; min-width:0;">
            <div class="mx-bk-label"><?= Loc::getMessage('MATRIX_RECORDS_FORM_PROCEDURE') ?></div>
            <div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown ui-ctl-w100 mx-bk-dropdown-wrap">
                <div class="ui-ctl-after ui-ctl-icon-angle"></div>
                <input type="text" class="ui-ctl-element" id="<?= $bk ?>-procedure-input" placeholder="<?= Loc::getMessage('MATRIX_COMMON_DROPDOWN_SELECT') ?>" autocomplete="off" readonly>
                <div class="mx-bk-dd-list" id="<?= $bk ?>-procedure-list"></div>
            </div>
        </div>

    </div>

    <!-- Слоты -->
    <div class="mx-bk-field" id="<?= $bk ?>-step-slots" style="display:none">
        <div class="mx-bk-label"><?= Loc::getMessage('MATRIX_RECORDS_FORM_DATETIME') ?></div>
        <div class="mx-bk-period-nav">
            <button class="ui-btn ui-btn-light ui-btn-sm" id="<?= $bk ?>-prev">&#8592;</button>
            <span class="mx-bk-period-title" id="<?= $bk ?>-period-title"></span>
            <button class="ui-btn ui-btn-light ui-btn-sm" id="<?= $bk ?>-next">&#8594;</button>
        </div>
        <div class="matrix-loading" id="<?= $bk ?>-slots-loading" style="display:none"><?= Loc::getMessage('MATRIX_COMMON_STATE_LOADING') ?></div>
        <div id="<?= $bk ?>-slots-body"></div>
        <div class="matrix-empty" id="<?= $bk ?>-slots-empty" style="display:none; color:#888; font-size:13px;"><?= Loc::getMessage('MATRIX_RECORDS_SLOTS_EMPTY') ?></div>
    </div>

    <!-- Кнопка -->
    <div id="<?= $bk ?>-step-confirm" style="display:none; margin-top:16px;">
        <button class="ui-btn ui-btn-success" id="<?= $bk ?>-btn-confirm"><?= Loc::getMessage('MATRIX_RECORDS_BTN_CONFIRM_BOOKING') ?></button>
    </div>

    <div id="<?= $bk ?>-error" class="matrix-error" style="display:none; margin-top:8px;"></div>
</div>

<script>
(function () {
    var uid       = <?= json_encode($uid) ?>;
    var bk        = <?= json_encode($bk) ?>;
    var contactId = <?= $contactId ?>;
    var patientId = <?= $patientId ?>;
    var ajaxUrl   = <?= json_encode($arResult['AJAX_URL']) ?>;
    var sessid    = <?= json_encode($arResult['SESSID']) ?>;
    var gridId    = <?= json_encode($arResult['RECORDS_GRID_ID']) ?>;
    var lazyloadUrl = <?= json_encode(\Bitrix\Main\Context::getCurrent()->getRequest()->getRequestUri()) ?>;
    var todayStr  = <?= json_encode(date('Y-m-d')) ?>;
    var mxLang = <?= json_encode([
        'dropdownSelect'         => Loc::getMessage('MATRIX_COMMON_DROPDOWN_SELECT'),
        'dropdownEmpty'          => Loc::getMessage('MATRIX_COMMON_DROPDOWN_EMPTY'),
        'dropdownLoading'        => Loc::getMessage('MATRIX_COMMON_DROPDOWN_LOADING'),
        'dropdownSelectDoctor'   => Loc::getMessage('MATRIX_RECORDS_DROPDOWN_SELECT_DOCTOR'),
        'doctorsNotFound'        => Loc::getMessage('MATRIX_RECORDS_DOCTORS_NOT_FOUND'),
        'doctorsLoadError'       => Loc::getMessage('MATRIX_RECORDS_DOCTORS_LOAD_ERROR'),
        'dropdownSelectSpecialty'=> Loc::getMessage('MATRIX_RECORDS_DROPDOWN_SELECT_SPECIALTY'),
        'dropdownSelectProcedure'=> Loc::getMessage('MATRIX_RECORDS_DROPDOWN_SELECT_PROCEDURE'),
        'bookingCreated'         => Loc::getMessage('MATRIX_RECORDS_SUCCESS_CREATED'),
        'networkError'           => Loc::getMessage('MATRIX_COMMON_ERROR_NETWORK'),
        'syncing'                => Loc::getMessage('MATRIX_COMMON_STATE_SYNCING'),
        'updated'                => Loc::getMessage('MATRIX_COMMON_STATUS_UPDATED'),
        'modalTitle'             => Loc::getMessage('MATRIX_RECORDS_MODAL_TITLE'),
        'detailModalTitle'       => Loc::getMessage('MATRIX_RECORDS_MODAL_DETAIL_TITLE'),
        'detailDatetime'         => Loc::getMessage('MATRIX_RECORDS_DETAIL_DATETIME'),
        'detailDoctor'           => Loc::getMessage('MATRIX_RECORDS_DETAIL_DOCTOR'),
        'detailProcedure'        => Loc::getMessage('MATRIX_RECORDS_DETAIL_PROCEDURE'),
        'detailStatus'           => Loc::getMessage('MATRIX_RECORDS_DETAIL_STATUS'),
        'detailType'             => Loc::getMessage('MATRIX_RECORDS_DETAIL_TYPE'),
        'close'                  => Loc::getMessage('MATRIX_COMMON_BTN_CLOSE'),
        'slotsLoading'           => Loc::getMessage('MATRIX_COMMON_STATE_LOADING'),
        'cancelRecord'           => Loc::getMessage('MATRIX_RECORDS_BTN_CANCEL') ?: 'Удалить',
    ]) ?>;

    /* ── Helpers ──────────────────────────────────────────────────────────── */

    function byId(id)         { return document.getElementById(id); }
    function bkEl(s)          { return byId(bk + '-' + s); }
    function bkShow(s)        { var e = bkEl(s); if (e) e.style.display = ''; }
    function bkHide(s)        { var e = bkEl(s); if (e) e.style.display = 'none'; }
    function bkEnableStep(s)  { var e = bkEl(s); if (e) e.classList.remove('mx-bk-step-disabled'); }
    function bkDisableStep(s) { var e = bkEl(s); if (e) e.classList.add('mx-bk-step-disabled'); }

    var ruMonthsGen = ['января','февраля','марта','апреля','мая','июня',
                       'июля','августа','сентября','октября','ноября','декабря'];
    var ruDays      = ['вс','пн','вт','ср','чт','пт','сб'];

    function fmtDay(ymd) {
        var d = new Date(ymd + 'T12:00:00');
        return d.getDate() + ' ' + ruMonthsGen[d.getMonth()] + ', ' + ruDays[d.getDay()];
    }
    function fmtPeriod(a, b) {
        var s = new Date(a + 'T12:00:00'), e = new Date(b + 'T12:00:00');
        return s.getDate() + ' ' + ruMonthsGen[s.getMonth()] + ' — ' +
               e.getDate() + ' ' + ruMonthsGen[e.getMonth()] + ' ' + e.getFullYear();
    }

    function post(action, extra) {
        var body = Object.assign({action: action, contact_id: contactId, patient_id: patientId, sessid: sessid}, extra || {});
        return fetch(ajaxUrl, {
            method:  'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body:    Object.keys(body).map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(body[k]);
            }).join('&')
        }).then(function (r) { return r.json(); });
    }

    /* ── MxDropdown ──────────────────────────────────────────────────────── */

    function MxDropdown(inputId, listId) {
        this._input  = byId(inputId);
        this._list   = byId(listId);
        this._items  = [];
        this._value  = null;
        this._extra  = {};
        this.onChange = null;
        var self = this;
        this._input.addEventListener('click', function () { self._toggle(); });
        document.addEventListener('click', function (e) {
            if (!self._input.contains(e.target) && !self._list.contains(e.target)) {
                self._close();
            }
        });
    }

    MxDropdown.prototype.setItems = function (items, placeholder) {
        this._items = items || [];
        this._value = null;
        this._extra = {};
        this._input.value = '';
        this._input.placeholder = placeholder || mxLang.dropdownSelect;
        this._list.innerHTML = '';
        this._close();
    };

    MxDropdown.prototype._render = function () {
        var self = this;
        this._list.innerHTML = '';
        if (!this._items.length) {
            var empty = document.createElement('div');
            empty.className = 'mx-bk-dd-empty';
            empty.textContent = mxLang.dropdownEmpty;
            this._list.appendChild(empty);
            return;
        }
        this._items.forEach(function (item) {
            var el = document.createElement('div');
            el.className = 'mx-bk-dd-item' + (item.value === self._value ? ' mx-bk-active' : '');
            el.textContent = item.label;
            el.addEventListener('click', function (e) {
                e.stopPropagation();
                self._value = item.value;
                self._extra = item.extra || {};
                self._input.value = item.label;
                self._close();
                if (typeof self.onChange === 'function') { self.onChange(item.value, item); }
            });
            self._list.appendChild(el);
        });
    };

    MxDropdown.prototype.getValue = function () { return this._value; };
    MxDropdown.prototype.getExtra = function () { return this._extra; };

    MxDropdown.prototype._open = function () {
        this._render();
        var rect = this._input.getBoundingClientRect();
        this._list.style.top   = rect.bottom + 'px';
        this._list.style.left  = rect.left + 'px';
        this._list.style.width = rect.width + 'px';
        this._list.classList.add('open');
    };

    MxDropdown.prototype._close = function () {
        this._list.classList.remove('open');
    };

    MxDropdown.prototype._toggle = function () {
        if (this._list.classList.contains('open')) { this._close(); } else { this._open(); }
    };

    MxDropdown.prototype.reset = function (placeholder) {
        this._value = null;
        this._extra = {};
        this._items = [];
        this._input.value = '';
        this._input.placeholder = placeholder || mxLang.dropdownSelect;
        this._list.innerHTML = '';
        this._close();
    };

    /* ── Booking state ────────────────────────────────────────────────────── */

    var bookingPopup    = null;
    var doctorsLoaded  = false;
    var chipMod        = null;
    var chipInstances = [];
    var ddDoctor, ddSpecialty, ddProcedure;

    var selDoctor    = null, selSpecialty = null, selProcedure = null, selSlot = null;
    var selProcedureDuration = 0;
    var currentStart = todayStr;
    var nextStart    = null;
    var prevStack    = [];

    /* ── Chip helper ──────────────────────────────────────────────────────── */

    function ensureChipMod() {
        if (chipMod) { return Promise.resolve(chipMod); }
        return BX.Runtime.loadExtension('ui.system.chip').then(function (m) { chipMod = m; return m; });
    }

    function bindSlotClick(cm, entry) {
        entry.chipEl.addEventListener('click', function () {
            chipInstances.forEach(function (e) {
                var newInst = new cm.Chip({
                    text:   e.slot.time,
                    size:    cm.ChipSize.Lg,
                    design: (e === entry) ? cm.ChipDesign.Tinted : cm.ChipDesign.TintedNoAccent,
                });
                var newEl = newInst.render();
                newEl.style.cursor = 'pointer';
                e.row.replaceChild(newEl, e.chipEl);
                e.chipEl   = newEl;
                e.chipInst = newInst;
                bindSlotClick(cm, e);
            });
            selSlot = entry.slot;
            bkShow('step-confirm');
            bkHide('error');
        });
    }

    /* ── Popup open / close ──────────────────────────────────────────────── */

    function openBookingPopup() {
        var popupId = bk + '-popup';
        var inner   = byId(bk + '-inner');

        if (!doctorsLoaded) {
            loadDoctors();
            doctorsLoaded = true;
        }

        var existing = BX.Main.PopupManager.getPopupById(popupId);
        if (existing) {
            inner.style.display = '';
            existing.show();
            return;
        }

        inner.style.display = '';

        bookingPopup = BX.Main.PopupManager.create({
            id:         popupId,
            title:      mxLang.modalTitle,
            content:    inner,
            closeIcon:  true,
            overlay:    { opacity: 30 },
            width:      1120,
            minHeight:  600,
            autoHide:   false,
            closeByEsc: true,
            events: {
                onClose: function () {
                    inner.style.display = 'none';
                    bkMod.reset();
                }
            }
        });
        bookingPopup.show();
    }

    /* ── Загрузка врачей ─────────────────────────────────────────────────── */

    function loadDoctors() {
        ddDoctor.setItems([], mxLang.dropdownLoading);
        post('get_doctors').then(function (d) {
            if (d.success && d.items && d.items.length) {
                ddDoctor.setItems(d.items.map(function (doc) {
                    return { value: doc.matrix_id, label: doc.full_name || ('Врач #' + doc.matrix_id) };
                }), mxLang.dropdownSelectDoctor);
            } else {
                ddDoctor.setItems([], mxLang.doctorsNotFound);
            }
        }).catch(function () {
            ddDoctor.setItems([], mxLang.doctorsLoadError);
        });
    }

    /* ── Booking logic ────────────────────────────────────────────────────── */

    var bkMod = {
        onDoctorChange: function () {
            selSpecialty = selProcedure = selSlot = null;
            ddSpecialty.reset(); ddProcedure.reset();
            bkDisableStep('step-specialty'); bkDisableStep('step-procedure');
            bkHide('step-slots'); bkHide('step-confirm'); bkHide('error');
            if (!selDoctor) { return; }

            post('get_doctor_specialties', { matrix_doctor_id: selDoctor }).then(function (d) {
                if (d.success && d.items && d.items.length) {
                    ddSpecialty.setItems(d.items.map(function (spec) {
                        return { value: spec.id, label: spec.name || ('Специальность #' + spec.id) };
                    }), mxLang.dropdownSelectSpecialty);
                    bkEnableStep('step-specialty');
                } else {
                    bkMod._loadProcedures(0);
                }
            });
        },

        onSpecialtyChange: function (val) {
            selSpecialty = val || null;
            selProcedure = selSlot = null;
            ddProcedure.reset();
            bkDisableStep('step-procedure');
            bkHide('step-slots'); bkHide('step-confirm'); bkHide('error');
            if (selSpecialty) { bkMod._loadProcedures(selSpecialty); }
        },

        _loadProcedures: function (specialtyId) {
            post('get_procedures', { matrix_doctor_id: selDoctor, specialty_id: specialtyId || 0 })
                .then(function (d) {
                    var items = [];
                    if (d.success && d.items) {
                        items = d.items.map(function (proc) {
                            return {
                                value: proc.matrix_id,
                                label: proc.name + (proc.cost ? ' (' + proc.cost + ' ₽)' : ''),
                                extra: { duration: proc.duration || 0 }
                            };
                        });
                    }
                    ddProcedure.setItems(items, mxLang.dropdownSelectProcedure);
                    bkEnableStep('step-procedure');
                });
        },

        onProcedureChange: function (val, item) {
            selProcedure         = val || null;
            selProcedureDuration = item ? (parseInt((item.extra || {}).duration, 10) || 0) : 0;
            selSlot              = null;
            bkHide('step-slots'); bkHide('step-confirm'); bkHide('error');
            if (!selProcedure) { return; }
            currentStart = todayStr; prevStack = []; nextStart = null;
            bkMod._loadSlots(currentStart);
        },

        _loadSlots: function (startDate) {
            bkShow('step-slots'); bkHide('step-confirm'); bkHide('slots-empty');
            bkEl('slots-body').innerHTML = '';
            bkShow('slots-loading');
            bkEl('period-title').textContent = mxLang.slotsLoading;
            selSlot = null;

            post('get_slots', {
                matrix_doctor_id:    selDoctor,
                matrix_procedure_id: selProcedure,
                start_date:          startDate,
                days:                14,
                duration:            selProcedureDuration
            }).then(function (d) {
                bkHide('slots-loading');
                nextStart = d.next_start || null;

                var days = d.slots_by_day ? Object.keys(d.slots_by_day) : [];
                if (days.length) {
                    bkEl('period-title').textContent = fmtPeriod(days[0], days[days.length - 1]);
                } else {
                    var endDate = new Date(startDate + 'T12:00:00');
                    endDate.setDate(endDate.getDate() + 13);
                    var endStr = endDate.getFullYear() + '-' +
                        String(endDate.getMonth() + 1).padStart(2, '0') + '-' +
                        String(endDate.getDate()).padStart(2, '0');
                    bkEl('period-title').textContent = fmtPeriod(startDate, endStr);
                }

                var body = bkEl('slots-body');
                body.innerHTML = '';

                if (!d.success || !d.slots_by_day || !days.length) {
                    bkShow('slots-empty');
                    return;
                }

                ensureChipMod().then(function (cm) {
                    chipInstances = [];
                    days.forEach(function (day) {
                        var slots = d.slots_by_day[day];
                        var block = document.createElement('div');
                        block.className = 'mx-bk-day-block';

                        var dayLabel = document.createElement('div');
                        dayLabel.className   = 'mx-bk-day-label';
                        dayLabel.textContent = fmtDay(day);
                        block.appendChild(dayLabel);

                        var row = document.createElement('div');
                        row.className = 'mx-bk-slot-row';

                        slots.forEach(function (slot) {
                            var inst = new cm.Chip({
                                text:   slot.time,
                                size:   cm.ChipSize.Lg,
                                design: cm.ChipDesign.TintedNoAccent,
                            });
                            var chipEl = inst.render();
                            chipEl.style.cursor = 'pointer';
                            var entry = { chipInst: inst, chipEl: chipEl, slot: slot, day: day, row: row };
                            chipInstances.push(entry);
                            bindSlotClick(cm, entry);
                            row.appendChild(chipEl);
                        });

                        block.appendChild(row);
                        body.appendChild(block);
                    });
                });
            });
        },

        prevPeriod: function () {
            if (!prevStack.length) { return; }
            nextStart    = currentStart;
            currentStart = prevStack.pop();
            bkMod._loadSlots(currentStart);
        },

        nextPeriod: function () {
            if (!nextStart) { return; }
            prevStack.push(currentStart);
            currentStart = nextStart;
            bkMod._loadSlots(currentStart);
        },

        confirm: function () {
            if (!selDoctor || !selProcedure || !selSlot) { return; }
            var btn = bkEl('btn-confirm');
            btn.classList.add('ui-btn-wait');
            btn.disabled = true;

            post('create_record', {
                matrix_doctor_id:    selDoctor,
                matrix_procedure_id: selProcedure,
                datetime:            selSlot.datetime,
                duration:            selProcedureDuration
            }).then(function (d) {
                btn.classList.remove('ui-btn-wait');
                btn.disabled = false;
                if (d.success) {
                    bookingPopup && bookingPopup.close();
                    bkMod.reset();
                    var st = byId(uid + '-book-status');
                    st.textContent = mxLang.bookingCreated;
                    st.className   = 'matrix-tag matrix-tag-ok';
                    setTimeout(function () { st.textContent = ''; st.className = ''; }, 4000);
                    recMod.sync();
                } else {
                    bkEl('error').textContent = d.message || 'Ошибка';
                    bkShow('error');
                }
            }).catch(function () {
                btn.classList.remove('ui-btn-wait');
                btn.disabled = false;
                bkEl('error').textContent = mxLang.networkError;
                bkShow('error');
            });
        },

        reset: function () {
            selDoctor = selSpecialty = selProcedure = selSlot = null;
            currentStart = todayStr; prevStack = []; nextStart = null;
            selProcedureDuration = 0;
            ddDoctor.reset(mxLang.dropdownSelectDoctor);
            ddSpecialty.reset();
            ddProcedure.reset();
            bkDisableStep('step-specialty'); bkDisableStep('step-procedure');
            bkHide('step-slots'); bkHide('step-confirm'); bkHide('error');
        }
    };

    /* ── Records logic ────────────────────────────────────────────────────── */

    var recMod = {
        sync: function () {
            var st = byId(uid + '-sync-status');
            st.textContent = mxLang.syncing;
            post('sync_patient').then(function (d) {
                st.textContent = d.success ? mxLang.updated : ('Ошибка: ' + d.message);
                if (d.success) {
                    if (window.BX && BX.Main && BX.Main.gridManager) {
                        var wrapper = BX.Main.gridManager.getById(gridId);
                        var gi = wrapper ? wrapper.instance : null;
                        if (gi && typeof gi.reload === 'function') { gi.reload(); }
                    }
                }
            });
        }
    };

    window[uid + 'mod'] = recMod;

    /* ── Init ─────────────────────────────────────────────────────────────── */

    BX.ready(function () {
        ddDoctor    = new MxDropdown(bk + '-doctor-input',    bk + '-doctor-list');
        ddSpecialty = new MxDropdown(bk + '-specialty-input', bk + '-specialty-list');
        ddProcedure = new MxDropdown(bk + '-procedure-input', bk + '-procedure-list');

        ddDoctor.onChange = function (val) {
            selDoctor = val ? parseInt(val, 10) : null;
            bkMod.onDoctorChange();
        };
        ddSpecialty.onChange = function (val) { bkMod.onSpecialtyChange(val); };
        ddProcedure.onChange = function (val, item) { bkMod.onProcedureChange(val, item); };

        byId(uid + '-open-booking').addEventListener('click', openBookingPopup);
        bkEl('prev').addEventListener('click', function () { bkMod.prevPeriod(); });
        bkEl('next').addEventListener('click', function () { bkMod.nextPeriod(); });
        bkEl('btn-confirm').addEventListener('click', function () { bkMod.confirm(); });
    });

    /* ── Grid baseUrl patch ───────────────────────────────────────────────── */

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

    /* ── Record detail popup ─────────────────────────────────────────────── */

    function showRecordDetail(data) {
        var popupId  = uid + '-detail-popup';
        var existing = BX.Main.PopupManager.getPopupById(popupId);
        if (existing) { existing.destroy(); }

        var wrap = document.createElement('div');
        wrap.style.cssText = 'padding:10px';

        [
            [mxLang.detailDatetime,  data.dt        || '—'],
            [mxLang.detailDoctor,    data.doctor    || '—'],
            [mxLang.detailProcedure, data.procedure || '—'],
            [mxLang.detailStatus,    data.status    || '—'],
            [mxLang.detailType,      data.type      || '—'],
        ].forEach(function (f) {
            var row = document.createElement('div');
            row.style.cssText = 'display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #f0f0f0';
            var lbl = document.createElement('div');
            lbl.style.cssText = 'width:120px;font-size:12px;color:#6d6d6d;flex-shrink:0';
            lbl.textContent = f[0];
            var val = document.createElement('div');
            val.style.cssText = 'font-size:13px;color:#333;font-weight:500';
            val.textContent = f[1];
            row.appendChild(lbl);
            row.appendChild(val);
            wrap.appendChild(row);
        });

        var foot = document.createElement('div');
        foot.style.cssText = 'display:flex;justify-content:flex-end;gap:8px;margin-top:16px';

        var closeBtn = document.createElement('button');
        closeBtn.textContent = mxLang.close;
        closeBtn.className   = 'ui-btn ui-btn-sm ui-btn-default';
        closeBtn.onclick = function () {
            BX.Main.PopupManager.getPopupById(popupId).close();
        };

        if (data.statusKey !== 'canceled') {
            var cancelBtn = document.createElement('button');
            cancelBtn.textContent = mxLang.cancelRecord;
            cancelBtn.className   = 'ui-btn ui-btn-sm ui-btn-danger';
            cancelBtn.style.cssText = 'background-color:#c62828;border-color:#c62828;color:#fff';
            cancelBtn.onclick = function () {
                cancelBtn.classList.add('ui-btn-wait');
                cancelBtn.disabled = true;
                closeBtn.disabled  = true;
                post('cancel_record', { matrix_record_id: data.id }).then(function (d) {
                    if (d.success) {
                        BX.Main.PopupManager.getPopupById(popupId).close();
                        if (window.BX && BX.Main && BX.Main.gridManager) {
                            var wrapper = BX.Main.gridManager.getById(gridId);
                            var gi = wrapper ? wrapper.instance : null;
                            if (gi && typeof gi.reload === 'function') { gi.reload(); }
                        }
                    } else {
                        cancelBtn.classList.remove('ui-btn-wait');
                        cancelBtn.disabled = false;
                        closeBtn.disabled  = false;
                        alert(d.message || 'Ошибка отмены записи');
                    }
                }).catch(function () {
                    cancelBtn.classList.remove('ui-btn-wait');
                    cancelBtn.disabled = false;
                    closeBtn.disabled  = false;
                    alert(mxLang.networkError);
                });
            };
            foot.appendChild(cancelBtn);
        }

        foot.appendChild(closeBtn);
        wrap.appendChild(foot);

        BX.Main.PopupManager.create({
            id:         popupId,
            title:      mxLang.detailModalTitle,
            content:    wrap,
            closeIcon:  true,
            overlay:    { opacity: 50 },
            width:      600,
            autoHide:   false,
            closeByEsc: true,
        }).show();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.mx-record-detail');
        if (!btn) { return; }
        e.stopPropagation();
        e.preventDefault();
        try { showRecordDetail(JSON.parse(btn.getAttribute('data-r'))); } catch (ex) {}
    }, true);

})();
</script>

<?php endif; ?>
