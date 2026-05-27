<?php

namespace Matrix\Integration\CrmList;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ContactListButton
{
    public static function onEpilog(): void
    {
        $uri = \Bitrix\Main\Context::getCurrent()->getRequest()->getRequestUri();

        if (!preg_match('#/crm/contact/(list|index)#i', $uri)) {
            return;
        }

        if (!\Bitrix\Main\Loader::includeModule('matrix.integration')) {
            return;
        }

        $url  = '/crm/matrix-turnouts/';
        $text = Loc::getMessage('MATRIX_CRM_LIST_BTN_TURNOUTS');

        echo '<script>
(function () {
    var reportUrl = ' . \Bitrix\Main\Web\Json::encode($url) . ';
    var btnText   = ' . \Bitrix\Main\Web\Json::encode($text) . ';

    function inject() {
        if (document.getElementById("mx-turnout-report-btn")) {
            return true;
        }
        var container = document.querySelector(".ui-toolbar-after-title-buttons");
        if (!container) {
            return false;
        }

        var btn = document.createElement("a");
        btn.id          = "mx-turnout-report-btn";
        btn.href        = reportUrl;
        btn.className   = "ui-btn ui-btn-primary";
        btn.textContent = btnText;
        container.appendChild(btn);
        return true;
    }

    function tryInject(n) {
        if (inject()) {
            return;
        }
        if (n > 0) {
            setTimeout(function () { tryInject(n - 1); }, 300);
        }
    }

    BX.ready(function () { tryInject(10); });
})();
</script>';
    }
}
