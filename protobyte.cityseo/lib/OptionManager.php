<?php
/**
 * Менеджер настроек модуля protobyte.cityseo.
 *
 * Сейчас реализован базовый механизм сохранения и получения значений.
 */
class OptionManager
{
    private const MODULE_ID = 'protobyte.cityseo';

    public static function getOption(string $name, string $default = ''): string
    {
        return COption::GetOptionString(self::MODULE_ID, $name, $default);
    }

    public static function setOption(string $name, string $value): bool
    {
        return COption::SetOptionString(self::MODULE_ID, $name, $value);
    }

    public static function save(array $options): void
    {
        foreach ($options as $name => $value) {
            self::setOption($name, $value);
        }
    }
}
