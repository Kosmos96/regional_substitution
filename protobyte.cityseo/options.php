<?php
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

$moduleId = 'protobyte.cityseo';
CModule::IncludeModule($moduleId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $enabled = isset($_POST['cityseo_enabled']) && $_POST['cityseo_enabled'] === 'Y' ? 'Y' : 'N';
    $sampleText = trim($_POST['cityseo_sample_text'] ?? '');

    COption::SetOptionString($moduleId, 'cityseo_enabled', $enabled);
    COption::SetOptionString($moduleId, 'cityseo_sample_text', $sampleText);

    echo CAdminMessage::ShowNote(Loc::getMessage('PROTOBYTE_CITYSEO_OPTIONS_SAVED'));
}

$arOptions = [
    ['cityseo_enabled', Loc::getMessage('PROTOBYTE_CITYSEO_OPTION_ENABLED'), 'N', ['checkbox']],
    ['cityseo_sample_text', Loc::getMessage('PROTOBYTE_CITYSEO_OPTION_SAMPLE_TEXT'), '', ['text', 50]],
];

$tabControl = new CAdminTabControl('tabControl', [
    [
        'DIV' => 'edit1',
        'TAB' => Loc::getMessage('PROTOBYTE_CITYSEO_OPTIONS_TAB_MAIN'),
        'TITLE' => Loc::getMessage('PROTOBYTE_CITYSEO_OPTIONS_TAB_MAIN_TITLE'),
    ],
]);

$tabControl->Begin();
?>
<form method="post" action="<?php echo htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>?mid=<?php echo urlencode($moduleId) ?>&lang=<?php echo LANGUAGE_ID ?>">
    <?php echo bitrix_sessid_post() ?>
    <?php $tabControl->BeginNextTab(); ?>

    <?php foreach ($arOptions as $option):
        $name = $option[0];
        $label = $option[1];
        $default = $option[2];
        $type = $option[3];
        $value = COption::GetOptionString($moduleId, $name, $default);
    ?>
        <tr>
            <td width="40%"><label for="<?php echo htmlspecialcharsbx($name) ?>"><?php echo htmlspecialcharsbx($label) ?></label></td>
            <td width="60%">
                <?php if ($type[0] === 'checkbox'): ?>
                    <input type="checkbox" id="<?php echo htmlspecialcharsbx($name) ?>" name="<?php echo htmlspecialcharsbx($name) ?>" value="Y" <?php echo $value === 'Y' ? 'checked' : '' ?>>
                <?php elseif ($type[0] === 'text'): ?>
                    <input type="text" size="<?php echo (int)$type[1] ?>" name="<?php echo htmlspecialcharsbx($name) ?>" value="<?php echo htmlspecialcharsbx($value) ?>">
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>

    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="save" value="<?php echo Loc::getMessage('PROTOBYTE_CITYSEO_OPTIONS_SAVE') ?>" class="adm-btn-save">
</form>
<?php $tabControl->End();
