<?php

namespace App\Enums;

/**
 * Corpus profile = a named documentation instruction the assistant serves
 * (kings5-docs = events platform, clams-docs = member registry).
 *
 * The backing value is a DURABLE domain identifier — it is persisted in
 * conversations, messages, generation metadata, the corpus manifest and config.
 * Therefore it is IMMUTABLE: you may change a profile's display label (in config),
 * never its `value`. Retire a profile via config `enabled=false`; never delete a
 * case or rename its value (that would orphan historical rows).
 */
enum CorpusProfile: string
{
    case Kings5Docs = 'kings5-docs';
    case ClamsDocs = 'clams-docs';
}
