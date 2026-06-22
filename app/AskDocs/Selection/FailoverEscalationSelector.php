<?php

namespace App\AskDocs\Selection;

use App\AskDocs\Contracts\EscalationSelector;

/**
 * Type-tagged failover selector for the escalation stage. Identical behaviour to
 * FailoverAnswerUnitSelector — the distinct type only lets AskDocs constructor-inject
 * the escalation chain separately from the primary one (no service locator).
 */
class FailoverEscalationSelector extends FailoverAnswerUnitSelector implements EscalationSelector {}
