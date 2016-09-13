<?php
namespace Slime\HttpCrawler;

use Slime\Http\Request;
use Slime\Http\Stream;

class RequestFactory
{
    public static function createGET($sUrl, $nCookie = null, $naHeader = null, $nsBody = null, $niIP = null)
    {
        return self::create('GET', $sUrl, $nCookie, $naHeader, $nsBody, $niIP);
    }

    public static function createPOST($sUrl, $nCookie = null, $naHeader = null, $nsBody = null, $niIP = null)
    {
        return self::create('POST', $sUrl, $nCookie, $naHeader, $nsBody, $niIP);
    }

    private static function create($sMethod, $sUrl, $nCookie = null, $naHeader = null, $nsBody = null, $niIP = null)
    {
        $nsHost = null;
        if ($niIP !== null) {
            $sRE = '#://(.*?)/#';
            if (!preg_match($sRE, $sUrl, $aMatch)) {
                $niIP = null;
            } else {
                $nsHost = $aMatch[1];
                $sUrl   = preg_replace('#://(.*?)/#', "://$niIP/", $sUrl);
            }
        }
        $aBlock = parse_url($sUrl);
        $Body   = new Stream('php://memory');
        if ($nsBody !== null) {
            $Body->write($nsBody);
        }
        $aHeader           = $naHeader === null ? [] : (array)$naHeader;
        $aHeader['Host'][] = $nsHost === null ? $aBlock['host'] : $nsHost;
        return new Request(
            $sMethod,
            (
                (isset($aBlock['path']) ? $aBlock['path'] : '/') .
                (isset($aBlock['query']) ? "?{$aBlock['query']}" : '') .
                (isset($aBlock['fragment']) ? "#{$aBlock['fragment']}" : '')
            ),
            '1.0',
            $aHeader,
            $Body,
            $sUrl
        );
    }
}