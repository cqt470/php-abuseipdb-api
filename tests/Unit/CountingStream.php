<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Unit;

    /**
     * Stream wrapper that forwards to the real file and counts how many times it is opened.
     * Usage: CountingStream::register(), then use CountingStream::PREFIX.$path as filename.
     */
    final class CountingStream
    {
        public const PREFIX = 'counting://';

        public static int $opens = 0;

        /** @var resource|null */
        public $context;

        /** @var resource */
        private $fp;

        public static function register(): void
        {
            if (!in_array('counting', stream_get_wrappers(), true)) {
                stream_wrapper_register('counting', self::class);
            }
            self::$opens = 0;
        }

        public static function unregister(): void
        {
            if (in_array('counting', stream_get_wrappers(), true)) {
                stream_wrapper_unregister('counting');
            }
        }

        private static function real(string $path): string
        {
            return substr($path, strlen(self::PREFIX));
        }

        public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
        {
            $fp = fopen(self::real($path), $mode);
            if ($fp === false) return false;

            $this->fp = $fp;
            self::$opens++;
            return true;
        }

        public function stream_read(int $count): string|false { return fread($this->fp, $count); }
        public function stream_write(string $data): int|false { return fwrite($this->fp, $data); }
        public function stream_eof(): bool { return feof($this->fp); }
        public function stream_tell(): int|false { return ftell($this->fp); }
        public function stream_seek(int $offset, int $whence): bool { return fseek($this->fp, $offset, $whence) === 0; }
        public function stream_flush(): bool { return fflush($this->fp); }
        public function stream_truncate(int $size): bool { return ftruncate($this->fp, $size); }
        public function stream_lock(int $operation): bool { return flock($this->fp, $operation); }
        public function stream_stat(): array|false { return fstat($this->fp); }
        public function stream_close(): void { fclose($this->fp); }

        public function url_stat(string $path, int $flags): array|false
        {
            $real = self::real($path);
            return file_exists($real) ? stat($real) : false;
        }
    }
