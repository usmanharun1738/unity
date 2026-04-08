<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Faculty = 'faculty';
    case Student = 'student';
    case DepartmentStaff = 'department-staff';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
