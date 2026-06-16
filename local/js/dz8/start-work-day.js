(function () {
	'use strict';

	var POPUP_ID = 'dz8-start-work-day-popup';

	/** @type {?BX.PopupWindow} */
	var popupInstance = null;

	BX.ready(init);

	/**
	 * @return {void}
	 */
	function init()
	{
		document.addEventListener('click', handleDocumentClick, true);
	}

	/**
	 * @param {MouseEvent} event
	 *
	 * @return {void}
	 */
	function handleDocumentClick(event)
	{
		var button = event.target.closest('button');

		if (!button || !isStartButton(button))
		{
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		showStartWorkDayPopup();
	}

	/**
	 * @param {HTMLElement} button
	 *
	 * @return {boolean}
	 */
	function isStartButton(button)
	{
		var texts = window.DZ8_START_WORK_DAY_TEXTS || {};
		var buttonText = button.textContent.replace(/\s+/g, ' ').trim();

		return Boolean(texts.buttonLabel) && buttonText === texts.buttonLabel;
	}

	/**
	 * @return {void}
	 */
	function showStartWorkDayPopup()
	{
		if (!popupInstance)
		{
			popupInstance = createPopup();
		}

		popupInstance.show();
	}

	/**
	 * @return {BX.PopupWindow}
	 */
	function createPopup()
	{
		var texts = window.DZ8_START_WORK_DAY_TEXTS || {};

		return new BX.PopupWindow(POPUP_ID, null, {
			titleBar: texts.title,
			content: BX.create('div', {
				props: {className: 'dz8-start-work-day-popup-text'},
				text: texts.text
			}),
			closeIcon: true,
			overlay: true,
			buttons: [
				new BX.PopupWindowButton({
					text: texts.confirmText,
					className: 'popup-window-button-accept',
					events: {
						click: function (event) {
							startWorkDay(event);
							popupInstance.close();
						}
					}
				})
			]
		});
	}

	/**
	 * @param {Event} event
	 *
	 * @return {void}
	 */
	function startWorkDay(event)
	{
		if (window.BXTIMEMAN && typeof window.BXTIMEMAN.OpenDay === 'function')
		{
			window.BXTIMEMAN.OpenDay(event);
		}
	}
})();
