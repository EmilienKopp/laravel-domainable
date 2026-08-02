<?php

namespace Splitstack\Domainable\Attributes;

use Attribute;

/**
 * Marks a model method as domain behavior, exposing it on the entity proxy.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Domain {}
