<?php

namespace Positiv\FilamentWebflow\Support;

class WebflowFieldData
{
    /**
     * Get a displayable value for a Webflow field (e.g. in table columns).
     * Image/File fields from Webflow API are often objects like {"url": "...", "fileId": "..."}.
     */
    public static function displayValue(mixed $value): ?string
    {
        if ($value === null || is_scalar($value)) {
            if ($value === null) {
                return null;
            }
            $str = (string) $value;

            return trim(strip_tags($str));
        }

        if (is_array($value)) {
            $url = self::extractUrlFromImageLike($value);
            if ($url !== null) {
                return $url;
            }
            // MultiImage: list of image objects
            if (isset($value[0]) && is_array($value[0])) {
                $url = self::extractUrlFromImageLike($value[0]);
                if ($url !== null) {
                    return $url;
                }
            }

            return self::firstScalarFromArray($value);
        }

        if (is_object($value)) {
            $arr = (array) $value;

            return self::extractUrlFromImageLike($arr) ?? self::firstScalarFromArray($arr);
        }

        return null;
    }

    /**
     * Extract URL from Webflow-style image/file object (url, fileUrl, src, etc.).
     *
     * @param  array<string, mixed>  $data
     */
    public static function extractUrlFromImageLike(array $data): ?string
    {
        foreach (['url', 'fileUrl', 'src', 'file', 'href'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    /**
     * Get value suitable for Webflow API fieldData (URL string for images when we have one).
     *
     * @param  array<string, mixed>  $data
     */
    public static function valueForWebflowApi(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            $url = self::extractUrlFromImageLike($value);
            if ($url !== null) {
                return $url;
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function firstScalarFromArray(array $data): ?string
    {
        foreach ($data as $v) {
            if (is_string($v) && $v !== '') {
                return $v;
            }
            if (is_numeric($v)) {
                return (string) $v;
            }
        }

        return null;
    }
}
