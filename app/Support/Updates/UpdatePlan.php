<?php

namespace App\Support\Updates;

/**
 * What installing a downloaded release will do to this site's files,
 * worked out before anything is touched.
 */
final readonly class UpdatePlan
{
    /**
     * @param  list<string>  $writes  files to add or replace
     * @param  list<string>  $deletes  old files the release no longer has
     * @param  list<string>  $changed  files the owner edited that the update replaces or removes
     * @param  list<string>  $unwritable  files or folders PHP may not write
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $zip,
        public string $sha256,
        public array $writes,
        public array $deletes,
        public array $changed,
        public array $unwritable,
        /** False when this site has no file list, so edits cannot be detected. */
        public bool $baseline,
        public string $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['from'],
            (string) $data['to'],
            (string) $data['zip'],
            (string) $data['sha256'],
            array_values((array) $data['writes']),
            array_values((array) $data['deletes']),
            array_values((array) $data['changed']),
            array_values((array) $data['unwritable']),
            (bool) $data['baseline'],
            (string) $data['created_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'zip' => $this->zip,
            'sha256' => $this->sha256,
            'writes' => $this->writes,
            'deletes' => $this->deletes,
            'changed' => $this->changed,
            'unwritable' => $this->unwritable,
            'baseline' => $this->baseline,
            'created_at' => $this->createdAt,
        ];
    }
}
