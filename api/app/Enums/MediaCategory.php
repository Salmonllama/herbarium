<?php

namespace App\Enums;

enum MediaCategory: string
{
    case SheetScan = 'sheet_scan';
    case FieldPhoto = 'field_photo';
    case Closeup = 'closeup';
    case Label = 'label';
    case Reference = 'reference';
}
