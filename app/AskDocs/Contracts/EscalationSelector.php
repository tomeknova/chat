<?php

namespace App\AskDocs\Contracts;

/**
 * Domain port for the OPTIONAL escalation stage: a selector bound to the fallback
 * provider only, used when the primary abstains and AskDocs retries on the full
 * corpus. A distinct type (vs AnswerUnitSelector) so it can be constructor-injected
 * into AskDocs without a service locator; resolves to null when no fallback is
 * configured (see AskDocsServiceProvider).
 */
interface EscalationSelector extends AnswerUnitSelector {}
