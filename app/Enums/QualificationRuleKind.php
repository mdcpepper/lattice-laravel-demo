<?php

namespace App\Enums;

enum QualificationRuleKind: string
{
    case HasAll = "has_all";
    case HasAny = "has_any";
    case HasNone = "has_none";
    case Group = "group";
}
