<?php

namespace App\Support\Updates;

use RuntimeException;

/**
 * The owner edited files that an update would replace or remove.
 */
class ChangedFilesException extends RuntimeException
{
    /**
     * @param  list<string>  $files
     */
    public function __construct(public readonly array $files)
    {
        parent::__construct('These files were changed on this site and the update replaces them: '.implode(', ', array_slice($files, 0, 5)).(count($files) > 5 ? '…' : ''));
    }
}
