BX.ready(function() {
    // Используем делегирование событий на document
    BX.bind(document, 'click', function(e) {
        var target = e.target || e.srcElement;
        
        // Проверяем, что клик был по элементу процедуры
        if (BX.hasClass(target, 'booking-procedure-item')) {
            // Получаем данные из data-атрибутов
            var procedureId = target.getAttribute('data-procedure-id');
            var procedureName = target.getAttribute('data-procedure-name');
            var doctorId = target.getAttribute('data-doctor-id');
            
            // Открываем попап
            openBookingPopup(procedureId, procedureName, doctorId);
        }
    });
});

function openBookingPopup(procedureId, procedureName, doctorId) {
    // Создаем контент для попапа
    var popupContent = '<div style="padding: 20px;">' +
        '<h3>Запись на процедуру: ' + BX.util.htmlspecialchars(procedureName) + '</h3>' +
        '<div style="margin: 15px 0;">' +
            '<label>ФИО пациента:</label><br>' +
            '<input type="text" id="patient-name" style="width: 100%; padding: 5px; margin-top: 5px;">' +
        '</div>' +
        '<div style="margin: 15px 0;">' +
            '<label>Дата и время записи:</label><br>' +
            '<input type="datetime-local" id="booking-time" style="width: 100%; padding: 5px; margin-top: 5px;">' +
        '</div>' +
    '</div>';
    
    // Создаем попап
    var popup = BX.PopupWindowManager.create('booking-popup-' + procedureId, null, {
        content: popupContent,
        width: 600,
        height: 390,
        titleBar: 'Запись на процедуру',
        closeIcon: true,
        buttons: [
            new BX.PopupWindowButton({
                text: 'Записаться',
                className: 'ui-btn ui-btn-success',
                events: {
                    click: function() {
                        // логика сохранения бронирования
                        saveBooking(procedureId, doctorId, popup);
                    }
                }
            }),
            new BX.PopupWindowButtonLink({
                text: 'Отмена',
                className: 'ui-btn ui-btn-link',
                events: {
                    click: function() {
                        this.popupWindow.close();
                    }
                }
            })
        ]
    });
    
    popup.show();
}

function saveBooking(procedureId, doctorId, popup) {
    var patientName = document.getElementById('patient-name').value;
    var bookingTime = document.getElementById('booking-time').value;
    
    // Валидация
    if (!patientName || !bookingTime) {
        alert('Заполните все поля!');
        return;
    }
    
    // AJAX запрос
    BX.ajax({
        url: '/local/ajax/create_booking.php',
        method: 'POST',
        data: {
            procedureId: procedureId,
            doctorId: doctorId,
            patientName: patientName,
            bookingTime: bookingTime
        },
        dataType: 'json',
        onsuccess: function(response) {
            if (response.success) {
                popup.close();
                alert(response.message);
                // Можно перенаправить на список бронирований
                window.location.href = '/services/lists/21/view/0/?list_section_id=';
            } else {
                alert('Ошибка: ' + response.error);
            }
        },
        onfailure: function() {
            alert('Ошибка соединения с сервером');
        }
    });
}