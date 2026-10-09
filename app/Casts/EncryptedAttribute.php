<?php

declare(strict_types=1);

namespace App\Casts;

/**
 * Marks a cast as encrypting its attribute at rest. AuditableObserver
 * never writes such an attribute to audit_logs (only `[changed]`).
 */
interface EncryptedAttribute {}
