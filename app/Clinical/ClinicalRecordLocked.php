<?php

namespace App\Clinical;

use RuntimeException;

/** Tentativa de alterar registro clínico finalizado (ou adendo). Correções entram como novo adendo. */
final class ClinicalRecordLocked extends RuntimeException {}
