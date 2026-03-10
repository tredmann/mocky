<?php

namespace App\Concerns;

use App\Models\User;

trait ResolvesImportFile
{
    public const MAX_IMPORT_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    /**
     * Validate an import file exists, is within size limits, and return its real path.
     *
     * Returns null and outputs an error if validation fails.
     */
    protected function validateImportFile(string $file): ?string
    {
        $realPath = realpath($file);

        if ($realPath === false) {
            $this->error("File [{$file}] not found.");

            return null;
        }

        if (filesize($realPath) > self::MAX_IMPORT_FILE_SIZE) {
            $this->error('File is too large (max 5MB).');

            return null;
        }

        return $realPath;
    }

    /**
     * Read and decode a JSON import file.
     *
     * Returns null and outputs an error if the file is not valid JSON.
     *
     * @return array<string, mixed>|null
     */
    protected function readJsonImportFile(string $realPath): ?array
    {
        $data = json_decode(file_get_contents($realPath), true);

        if (! is_array($data)) {
            $this->error('Invalid JSON file.');

            return null;
        }

        return $data;
    }

    /**
     * Resolve a user by email, ID, or default to the first user.
     *
     * Returns null and outputs an error if no user is found.
     */
    protected function resolveUser(?string $identifier): ?User
    {
        if (! $identifier) {
            $this->error('--user is required. Use --user=<email|id> to specify a user.');

            return null;
        }

        $user = User::find($identifier) ?? User::where('email', $identifier)->first();

        if (! $user) {
            $this->error("User [{$identifier}] not found.");

            return null;
        }

        return $user;
    }
}
