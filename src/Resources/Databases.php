<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

use Cipi\Sdk\Exception\CipiException;
use Cipi\Sdk\Exception\ConfigurationException;

final class Databases extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function engines(): array
    {
        return $this->client->get('/dbs/engines');
    }

    /**
     * @return array<string, mixed>
     */
    public function installEngine(string $engine): array
    {
        return $this->client->post('/dbs/engines/install', [
            'engine' => $engine,
        ]);
    }

    /**
     * Every database. With cipi/api ≥ 1.39.0 and Cipi CLI ≥ 5.9.0 each one
     * carries app (the app it is shown under, or null), link and size_mb.
     *
     * @return array<string, mixed>
     */
    public function list(?string $engine = null): array
    {
        return $this->client->get('/dbs', $this->withoutNulls([
            'engine' => $engine,
        ]));
    }

    /**
     * Queue the creation of a database. Returns a job (db-create).
     *
     * $app shows the database as that app's from the start (see link()).
     * Requires cipi/api ≥ 1.39.0 and Cipi CLI ≥ 5.9.0: on an older core the
     * panel answers 503 (ServerException) instead of creating it unlinked;
     * 404 (NotFoundException) when the app does not exist.
     *
     * @return array<string, mixed>
     */
    public function create(string $name, ?string $engine = null, ?string $app = null): array
    {
        return $this->client->post('/dbs', $this->withoutNulls([
            'name' => $name,
            'engine' => $engine,
            'app' => $app,
        ]));
    }

    /**
     * Show a database as an app's. A label in the app's entry: the database
     * keeps its user and password, and stays when the app is deleted. A
     * database is linked to one app at most. Synchronous:
     * {"data": {database, app, linked: true, message}}.
     *
     * Ability dbs-manage. Requires cipi/api ≥ 1.39.0 and Cipi CLI ≥ 5.9.0
     * (503 on an older core). What the CLI refuses is a 422 with its message.
     *
     * @return array<string, mixed>
     */
    public function link(string $name, string $app, ?string $engine = null): array
    {
        return $this->client->post($this->databasePath($name).'/link', $this->withoutNulls([
            'app' => $app,
            'engine' => $engine,
        ]));
    }

    /**
     * Remove the link between a database and its app. The database itself is
     * not touched. Synchronous: {"data": {database, app: null, linked: false, message}}.
     *
     * @return array<string, mixed>
     */
    public function unlink(string $name, ?string $engine = null): array
    {
        return $this->client->delete($this->databasePath($name).'/link', $this->engineBody($engine));
    }

    /**
     * @return array<string, mixed>
     */
    public function backup(string $name, ?string $engine = null): array
    {
        return $this->client->post($this->databasePath($name).'/backup', $this->engineBody($engine));
    }

    /**
     * Restore a database from a .sql.gz that is already on the server ($file
     * is its path there). Returns a job (db-restore). It replaces the
     * database's contents.
     *
     * For a dump on the machine this code runs on, see restoreLocal().
     *
     * @return array<string, mixed>
     */
    public function restore(string $name, string $file, ?string $engine = null): array
    {
        return $this->client->post($this->databasePath($name).'/restore', $this->withoutNulls([
            'file' => $file,
            'engine' => $engine,
        ]));
    }

    /**
     * Restore a database from a dump uploaded through the API ($uploadId from
     * upload() or startUpload()). Returns a job (db-restore). It replaces the
     * database's contents. The panel deletes the upload when the job ends,
     * whatever its result.
     *
     * 404 (NotFoundException) for an unknown upload, 422 (ValidationException)
     * for one that is incomplete or whose content does not match its name
     * (a .sql.gz that is not gzip, a .sql that is).
     *
     * @return array<string, mixed>
     */
    public function restoreUpload(string $name, string $uploadId, ?string $engine = null): array
    {
        return $this->client->post($this->databasePath($name).'/restore', $this->withoutNulls([
            'upload_id' => $uploadId,
            'engine' => $engine,
        ]));
    }

    /**
     * Upload a local dump and restore the database from it: upload() and then
     * restoreUpload(). Returns the db-restore job.
     *
     * @return array<string, mixed>
     */
    public function restoreLocal(string $name, string $path, ?string $engine = null): array
    {
        $uploadId = (string) $this->upload($path)['data']['upload_id'];

        try {
            return $this->restoreUpload($name, $uploadId, $engine);
        } catch (\Throwable $exception) {
            // Nothing was queued, so no job will delete the dump: do it here.
            $this->cancelQuietly($uploadId);

            throw $exception;
        }
    }

    /**
     * Limits for a dump upload: {"data": {max_size, chunk_size, extensions}},
     * sizes in bytes, extensions [".sql", ".sql.gz"].
     *
     * The upload methods need the ability dbs-manage, cipi/api ≥ 1.39.0 and
     * Cipi CLI ≥ 5.9.0 (503 on an older core).
     *
     * @return array<string, mixed>
     */
    public function uploadLimits(): array
    {
        return $this->client->get('/dbs/uploads/limits');
    }

    /**
     * Upload a dump from the machine this code runs on, in the chunks the
     * panel asks for, so that no request has to hold the whole file. Returns
     * the panel's answer to the last chunk: {"data": {upload_id, received,
     * size, next, complete: true}}. Pass data.upload_id to restoreUpload().
     *
     * $path is a .sql or .sql.gz file. $filename is the name the panel sees
     * (it reads the format from the extension); the file's own by default.
     *
     * One chunk is read into memory at a time (8 MB on a stock panel). Each is
     * one request under the client's timeout: raise it on a slow uplink.
     * When a request fails the upload is cancelled on the panel and the
     * exception is thrown again. What is uploaded and never restored is
     * deleted by the panel after a day.
     *
     * 422 (ValidationException) for a file that is not .sql or .sql.gz or is
     * larger than data.max_size of uploadLimits(); 507 (ServerException) when
     * the server has no room for it.
     *
     * @return array<string, mixed>
     */
    public function upload(string $path, ?string $filename = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new ConfigurationException('The dump file does not exist or is not readable.');
        }
        $size = filesize($path);
        if ($size === false || $size < 1) {
            throw new ConfigurationException('The dump file is empty.');
        }

        $started = $this->startUpload($filename ?? basename($path), $size);
        $id = $started['data']['upload_id'] ?? null;
        $chunkSize = $started['data']['chunk_size'] ?? null;
        if (! is_string($id) || $id === '' || ! is_int($chunkSize) || $chunkSize < 1) {
            throw new CipiException('The panel did not return an upload id and a chunk size.', 0, $started, 'POST', '/dbs/uploads');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->cancelQuietly($id);

            throw new ConfigurationException('The dump file could not be opened.');
        }

        $last = null;
        try {
            for ($index = 0; ; $index++) {
                $chunk = stream_get_contents($handle, $chunkSize);
                if ($chunk === false) {
                    throw new ConfigurationException('The dump file could not be read.');
                }
                if ($chunk === '') {
                    break;
                }
                $last = $this->uploadChunk($id, $index, $chunk);
            }
        } catch (\Throwable $exception) {
            $this->cancelQuietly($id);

            throw $exception;
        } finally {
            fclose($handle);
        }

        if ($last === null || ($last['data']['complete'] ?? false) !== true) {
            $this->cancelQuietly($id);

            throw new CipiException('The upload ended before the panel had the whole dump (did the file change while it was read?).', 0, $last ?? [], 'POST', '/dbs/uploads/'.$id.'/chunks');
        }

        return $last;
    }

    /**
     * Start an upload by hand: the dump's name (.sql or .sql.gz) and its size
     * in bytes. 201 with {"data": {upload_id, size, chunk_size, chunks, max_size}}.
     * Then uploadChunk() once per chunk of chunk_size bytes, in order, and
     * restoreUpload(). upload() does all of it for a local file.
     *
     * @return array<string, mixed>
     */
    public function startUpload(string $filename, int $size): array
    {
        return $this->client->post('/dbs/uploads', [
            'filename' => $filename,
            'size' => $size,
        ]);
    }

    /**
     * Send one chunk: its index, from 0, and its bytes (at most chunk_size).
     * {"data": {upload_id, received, size, next, complete}}.
     *
     * Chunks are appended in order: a wrong index is a 409 (ConflictException)
     * whose message names the one the panel expects. 404 for an unknown
     * upload, 422 for a chunk that is empty, too large, or more than the size
     * declared.
     *
     * @return array<string, mixed>
     */
    public function uploadChunk(string $uploadId, int $index, string $contents): array
    {
        return $this->client->postMultipart(
            '/dbs/uploads/'.$this->client->segment($uploadId).'/chunks',
            ['index' => $index],
            ['chunk' => ['contents' => $contents, 'filename' => 'chunk']],
        );
    }

    /**
     * Cancel an upload and delete what arrived.
     *
     * @return array<string, mixed>
     */
    public function cancelUpload(string $uploadId): array
    {
        return $this->client->delete('/dbs/uploads/'.$this->client->segment($uploadId));
    }

    /**
     * @return array<string, mixed>
     */
    public function password(string $name, ?string $engine = null): array
    {
        return $this->client->post($this->databasePath($name).'/password', $this->engineBody($engine));
    }

    private function databasePath(string $name): string
    {
        return '/dbs/'.$this->client->segment($name);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function engineBody(?string $engine): ?array
    {
        return $engine === null ? null : ['engine' => $engine];
    }

    /**
     * The upload already failed: what matters is that error, not whether the
     * panel could also be told to drop the pieces (it does after a day).
     */
    private function cancelQuietly(string $uploadId): void
    {
        try {
            $this->cancelUpload($uploadId);
        } catch (\Throwable) {
        }
    }
}
