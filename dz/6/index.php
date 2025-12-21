<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle('Вывод Докторов с процедурами');

use Models\DoctorTable;
use Models\ProcedureTable;
use Bitrix\Main\Loader;

Loader::includeModule('iblock');

$selectedDoctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
?>

<style>
    .doctors-list {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 30px;
    }
    .doctor-card {
        border: 1px solid #ddd;
        padding: 15px;
        border-radius: 5px;
        width: 200px;
        text-align: center;
    }
    .doctor-link {
        display: block;
        padding: 10px;
        background: #f5f5f5;
        color: #333;
        text-decoration: none;
        border-radius: 3px;
        transition: background 0.3s;
    }
    .doctor-link:hover {
        background: #e0e0e0;
        text-decoration: none;
    }
    .doctor-link.active {
        background: #007bff;
        color: white;
    }
    .procedures-list {
        margin-top: 20px;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 5px;
    }
    .procedure-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
    }
    .back-link {
        display: inline-block;
        margin-bottom: 20px;
        padding: 8px 16px;
        background: #6c757d;
        color: white;
        text-decoration: none;
        border-radius: 3px;
    }
    .back-link:hover {
        background: #5a6268;
        color: white;
        text-decoration: none;
    }
</style>
<div class="doctors-container">
    <?php
    try {
        // 1. Получаем список всех врачей
        $doctors = \Bitrix\Iblock\ElementTable::getList([
            'select' => ['ID', 'NAME', 'PREVIEW_TEXT'],
            'filter' => [
                '=IBLOCK_ID' => DoctorTable::IBLOCK_ID,
                '=ACTIVE' => 'Y'
            ],
            'order' => ['NAME' => 'ASC']
        ])->fetchCollection();
        
        // Кнопка "Назад к списку" если выбран врач
        if ($selectedDoctorId > 0) {
            echo '<a href="' . $APPLICATION->GetCurPage() . '" class="back-link">&larr; Назад к списку врачей</a>';
        }
        
        // 2. Выводим список врачей как ссылки
        #TODO Если врачей дофига, то имеет смыл проверять Get и не выводить этот блок на детальке врача.
        
        echo '<div class="doctors-list">';
        
        foreach ($doctors as $doctor) {
            $isActive = ($selectedDoctorId == $doctor->getId());
            $doctorUrl = $APPLICATION->GetCurPage() . '?doctor_id=' . $doctor->getId();
            
            echo '<div class="doctor-card">';
            echo '<a href="' . $doctorUrl . '" class="doctor-link' . ($isActive ? ' active' : '') . '">';
            echo '<strong>' . htmlspecialchars($doctor->getName()) . '</strong>';            
            echo '</a>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // 3. Если выбран конкретный врач - показываем его процедуры
        if ($selectedDoctorId > 0) {
            $selectedDoctor = null;
            
            // Находим выбранного врача
            foreach ($doctors as $doctor) {
                if ($doctor->getId() == $selectedDoctorId) {
                    $selectedDoctor = $doctor;
                    break;
                }
            }
            
            if ($selectedDoctor) {
                echo '<div class="selected-doctor">';
                echo '<h2>Процедуры врача: ' . htmlspecialchars($selectedDoctor->getName()) . '</h2>';
                
                // Получаем процедуры выбранного врача
                $propertyRecords = DoctorTable::getList([
                    'select' => ['PROCEDURE'],
                    'filter' => ['=IBLOCK_ELEMENT_ID' => $selectedDoctorId],
                    'limit' => 1
                ])->fetchAll();
                
                if (!empty($propertyRecords) && !empty($propertyRecords[0]['PROCEDURE'])) {
                    $procedureIds = $propertyRecords[0]['PROCEDURE'];
                    
                    // Если не массив - делаем массивом
                    if (!is_array($procedureIds)) {
                        $procedureIds = [$procedureIds];
                    }
                    
                    // Получаем информацию о процедурах
                    if (!empty($procedureIds)) {
                        $procedures = \Bitrix\Iblock\ElementTable::getList([
                            'select' => ['ID', 'NAME'],
                            'filter' => [
                                '@ID' => $procedureIds,
                                '=ACTIVE' => 'Y'
                            ],
                            'order' => ['NAME' => 'ASC']
                        ])->fetchCollection();
                        
                        echo '<div class="procedures-list">';
                        
                        if ($procedures->count() > 0) {
                            echo '<p>Процедуры (' . $procedures->count() . '):</p>';
                            
                            foreach ($procedures as $procedure) {
                                echo '<div class="procedure-item">';
                                echo '<h4>' . htmlspecialchars($procedure->getName()) . '</h4>';  
                                echo '</div>';
                            }
                        } else {
                            echo '<p>Нет активных процедур или информация о процедурах не найдена.</p>';
                            echo '<p>ID процедур из свойства: ' . implode(', ', $procedureIds) . '</p>';
                        }
                        
                        echo '</div>';
                    }
                } else {
                    echo '<div class="procedures-list">';
                    echo '<p>У этого врача нет процедур.</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            } else {
                echo '<div class="alert alert-warning">Врач с ID ' . $selectedDoctorId . ' не найден.</div>';
            }
        } else {
            // 4. Если врач не выбран - показываем общую информацию
            echo '<div style="margin-top: 30px; padding: 20px; background: #f0f8ff; border-radius: 5px;">';
            echo '<h3>Информация</h3>';
            echo '<p>Всего врачей: ' . $doctors->count() . '</p>';
            echo '<p>Выберите врача из списка выше, чтобы увидеть его процедуры.</p>';
            echo '</div>';
        }
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>
</div>


<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>