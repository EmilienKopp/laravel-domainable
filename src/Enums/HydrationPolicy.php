<?php

namespace Splitstack\Domainable\Enums;

enum HydrationPolicy
{
    case Strict;
    case Lenient;
    case Quarantine;
    case AutoCorrect;
}
