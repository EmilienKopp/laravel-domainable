<?php

namespace Splitstack\Domainable\Attributes;

use Attribute;

/**
 * Marks a model method as domain behavior, exposing it on the entity proxy.
 *
 * Anything not marked stays hidden from the entity surface. The default is
 * deny: the entity only forwards methods you opt in with this attribute.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Domain {}
