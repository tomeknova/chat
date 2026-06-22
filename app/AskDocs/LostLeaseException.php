<?php

namespace App\AskDocs;

use RuntimeException;

/**
 * Thrown when an executor tries to finalize a generation it no longer owns:
 * its lease expired mid-flight and another executor reclaimed the operation via
 * CAS (decision R). The stale executor must abort WITHOUT writing a result —
 * the new owner produces the authoritative answer. Caught in AskDocs::process()
 * and surfaced as a transient "busy" degradation (never marked Failed).
 */
class LostLeaseException extends RuntimeException {}
