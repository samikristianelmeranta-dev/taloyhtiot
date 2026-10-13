<?php

declare(strict_types=1);

namespace Kilpailutus\Application;

/** Apuri syötteen lukemiseen ja virheiden keräämiseen kenttäkohtaisesti. */
final class Input
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function text(string $key, bool $required, int $maxLength, int $minLength = 0): ?string
    {
        $raw = $this->data[$key] ?? null;
        if ($raw !== null && !is_string($raw) && !is_int($raw) && !is_float($raw)) {
            $this->errors[$key] = 'Virheellinen arvo.';

            return null;
        }
        // Tallennetaan pelkkänä tekstinä; HTML poistetaan jo syötteessä.
        $value = trim(strip_tags((string) ($raw ?? '')));
        if ($value === '') {
            if ($required) {
                $this->errors[$key] = 'Pakollinen tieto.';
            }

            return $required ? null : '';
        }
        if (mb_strlen($value) > $maxLength) {
            $this->errors[$key] = sprintf('Enintään %d merkkiä.', $maxLength);
        } elseif (mb_strlen($value) < $minLength) {
            $this->errors[$key] = sprintf('Vähintään %d merkkiä.', $minLength);
        }

        return $value;
    }

    public function date(string $key): ?\DateTimeImmutable
    {
        $raw = $this->data[$key] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        $date = is_string($raw) ? Dates::parseDate($raw) : null;
        if ($date === null) {
            $this->errors[$key] = 'Anna päivämäärä muodossa VVVV-KK-PP.';
        }

        return $date;
    }

    public function dateTime(string $key, bool $required): ?\DateTimeImmutable
    {
        $raw = $this->data[$key] ?? null;
        if ($raw === null || $raw === '') {
            if ($required) {
                $this->errors[$key] = 'Pakollinen tieto.';
            }

            return null;
        }
        $dt = is_string($raw) ? Dates::parseDateTime($raw) : null;
        if ($dt === null) {
            $this->errors[$key] = 'Anna aika muodossa VVVV-KK-PPTtt:mm.';
        }

        return $dt;
    }

    public function int(string $key, int $min, int $max, int $default): int
    {
        $raw = $this->data[$key] ?? null;
        if ($raw === null || $raw === '') {
            return $default;
        }
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            $this->errors[$key] = 'Anna kokonaisluku.';

            return $default;
        }
        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            $this->errors[$key] = sprintf('Arvon on oltava välillä %d–%d.', $min, $max);
        }

        return $value;
    }

    /** Euromäärä, esim. "12500", "12500.50" tai "12 500,50". Palauttaa sentit. */
    public function euroCents(string $key, bool $required): ?int
    {
        $raw = $this->data[$key] ?? null;
        if ($raw === null || $raw === '') {
            if ($required) {
                $this->errors[$key] = 'Pakollinen tieto.';
            }

            return null;
        }
        if (!is_string($raw) && !is_int($raw)) {
            // Liukulukuja ei hyväksytä pyöristysvirheiden vuoksi.
            $this->errors[$key] = 'Anna summa merkkijonona, esim. "12500,00".';

            return null;
        }
        $normalized = str_replace([' ', "\u{00A0}"], '', (string) $raw);
        if (!preg_match('/^(\d{1,10})(?:[.,](\d{1,2}))?$/', $normalized, $m)) {
            $this->errors[$key] = 'Anna summa euroina, esim. 12500,00.';

            return null;
        }
        $cents = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
        if ($cents <= 0) {
            $this->errors[$key] = 'Summan on oltava suurempi kuin nolla.';
        }

        return $cents;
    }

    public function oneOf(string $key, array $allowed, bool $required): ?string
    {
        $raw = $this->data[$key] ?? null;
        if ($raw === null || $raw === '') {
            if ($required) {
                $this->errors[$key] = 'Pakollinen tieto.';
            }

            return null;
        }
        if (!is_string($raw) || !in_array($raw, $allowed, true)) {
            $this->errors[$key] = 'Tuntematon arvo.';

            return null;
        }

        return $raw;
    }

    public function addError(string $key, string $message): void
    {
        $this->errors[$key] = $message;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
