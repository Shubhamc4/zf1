<?php

/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 *
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 *
 * @version    $Id$
 */

/** Internally used classes */
require_once 'Zend/Pdf/Element/Name.php';

/** Zend_Pdf_Resource_Font_Simple_Standard */
require_once 'Zend/Pdf/Resource/Font/Simple/Standard.php';

/**
 * Implementation for the standard PDF font Symbol.
 *
 * This class was generated automatically using the font information and metric
 * data contained in the Adobe Font Metric (AFM) files, available here:
 * {@link http://partners.adobe.com/public/developer/en/pdf/Core14_AFMs.zip}
 *
 * The PHP script used to generate this class can be found in the /tools
 * directory of the framework distribution. If you need to make modifications to
 * this class, chances are the same modifications are needed for the rest of the
 * standard fonts. You should modify the script and regenerate the classes
 * instead of changing this class file by hand.
 *
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Pdf_Resource_Font_Simple_Standard_Symbol extends Zend_Pdf_Resource_Font_Simple_Standard
{
    /**** Instance Variables ****/

    /**
     * Array for conversion from local encoding to special font encoding.
     * See {@link encodeString()}.
     *
     * @var array
     */
    protected $_toFontEncoding = [
        0x20 => "\x20",   0x21 => "\x21", 0x2200 => "\x22",   0x23 => "\x23",
        0x2203 => "\x24",   0x25 => "\x25",   0x26 => "\x26", 0x220B => "\x27",
        0x28 => "\x28",   0x29 => "\x29", 0x2217 => "\x2a",   0x2B => "\x2b",
        0x2C => "\x2c", 0x2212 => "\x2d",   0x2E => "\x2e",   0x2F => "\x2f",
        0x30 => "\x30",   0x31 => "\x31",   0x32 => "\x32",   0x33 => "\x33",
        0x34 => "\x34",   0x35 => "\x35",   0x36 => "\x36",   0x37 => "\x37",
        0x38 => "\x38",   0x39 => "\x39",   0x3A => "\x3a",   0x3B => "\x3b",
        0x3C => "\x3c",   0x3D => "\x3d",   0x3E => "\x3e",   0x3F => "\x3f",
        0x2245 => "\x40", 0x0391 => "\x41", 0x0392 => "\x42", 0x03A7 => "\x43",
        0x2206 => "\x44", 0x0395 => "\x45", 0x03A6 => "\x46", 0x0393 => "\x47",
        0x0397 => "\x48", 0x0399 => "\x49", 0x03D1 => "\x4a", 0x039A => "\x4b",
        0x039B => "\x4c", 0x039C => "\x4d", 0x039D => "\x4e", 0x039F => "\x4f",
        0x03A0 => "\x50", 0x0398 => "\x51", 0x03A1 => "\x52", 0x03A3 => "\x53",
        0x03A4 => "\x54", 0x03A5 => "\x55", 0x03C2 => "\x56", 0x2126 => "\x57",
        0x039E => "\x58", 0x03A8 => "\x59", 0x0396 => "\x5a",   0x5B => "\x5b",
        0x2234 => "\x5c",   0x5D => "\x5d", 0x22A5 => "\x5e",   0x5F => "\x5f",
        0xF8E5 => "\x60", 0x03B1 => "\x61", 0x03B2 => "\x62", 0x03C7 => "\x63",
        0x03B4 => "\x64", 0x03B5 => "\x65", 0x03C6 => "\x66", 0x03B3 => "\x67",
        0x03B7 => "\x68", 0x03B9 => "\x69", 0x03D5 => "\x6a", 0x03BA => "\x6b",
        0x03BB => "\x6c",   0xB5 => "\x6d", 0x03BD => "\x6e", 0x03BF => "\x6f",
        0x03C0 => "\x70", 0x03B8 => "\x71", 0x03C1 => "\x72", 0x03C3 => "\x73",
        0x03C4 => "\x74", 0x03C5 => "\x75", 0x03D6 => "\x76", 0x03C9 => "\x77",
        0x03BE => "\x78", 0x03C8 => "\x79", 0x03B6 => "\x7a",   0x7B => "\x7b",
        0x7C => "\x7c",   0x7D => "\x7d", 0x223C => "\x7e", 0x20AC => "\xa0",
        0x03D2 => "\xa1", 0x2032 => "\xa2", 0x2264 => "\xa3", 0x2044 => "\xa4",
        0x221E => "\xa5", 0x0192 => "\xa6", 0x2663 => "\xa7", 0x2666 => "\xa8",
        0x2665 => "\xa9", 0x2660 => "\xaa", 0x2194 => "\xab", 0x2190 => "\xac",
        0x2191 => "\xad", 0x2192 => "\xae", 0x2193 => "\xaf",   0xB0 => "\xb0",
        0xB1 => "\xb1", 0x2033 => "\xb2", 0x2265 => "\xb3",   0xD7 => "\xb4",
        0x221D => "\xb5", 0x2202 => "\xb6", 0x2022 => "\xb7",   0xF7 => "\xb8",
        0x2260 => "\xb9", 0x2261 => "\xba", 0x2248 => "\xbb", 0x2026 => "\xbc",
        0xF8E6 => "\xbd", 0xF8E7 => "\xbe", 0x21B5 => "\xbf", 0x2135 => "\xc0",
        0x2111 => "\xc1", 0x211C => "\xc2", 0x2118 => "\xc3", 0x2297 => "\xc4",
        0x2295 => "\xc5", 0x2205 => "\xc6", 0x2229 => "\xc7", 0x222A => "\xc8",
        0x2283 => "\xc9", 0x2287 => "\xca", 0x2284 => "\xcb", 0x2282 => "\xcc",
        0x2286 => "\xcd", 0x2208 => "\xce", 0x2209 => "\xcf", 0x2220 => "\xd0",
        0x2207 => "\xd1", 0xF6DA => "\xd2", 0xF6D9 => "\xd3", 0xF6DB => "\xd4",
        0x220F => "\xd5", 0x221A => "\xd6", 0x22C5 => "\xd7",   0xAC => "\xd8",
        0x2227 => "\xd9", 0x2228 => "\xda", 0x21D4 => "\xdb", 0x21D0 => "\xdc",
        0x21D1 => "\xdd", 0x21D2 => "\xde", 0x21D3 => "\xdf", 0x25CA => "\xe0",
        0x2329 => "\xe1", 0xF8E8 => "\xe2", 0xF8E9 => "\xe3", 0xF8EA => "\xe4",
        0x2211 => "\xe5", 0xF8EB => "\xe6", 0xF8EC => "\xe7", 0xF8ED => "\xe8",
        0xF8EE => "\xe9", 0xF8EF => "\xea", 0xF8F0 => "\xeb", 0xF8F1 => "\xec",
        0xF8F2 => "\xed", 0xF8F3 => "\xee", 0xF8F4 => "\xef", 0x232A => "\xf1",
        0x222B => "\xf2", 0x2320 => "\xf3", 0xF8F5 => "\xf4", 0x2321 => "\xf5",
        0xF8F6 => "\xf6", 0xF8F7 => "\xf7", 0xF8F8 => "\xf8", 0xF8F9 => "\xf9",
        0xF8FA => "\xfa", 0xF8FB => "\xfb", 0xF8FC => "\xfc", 0xF8FD => "\xfd",
        0xF8FE => "\xfe"];

    /**
     * Array for conversion from special font encoding to local encoding.
     * See {@link decodeString()}.
     *
     * @var array
     */
    protected $_fromFontEncoding = [
        0x20 => "\x00\x20",   0x21 => "\x00\x21",   0x22 => "\x22\x00",
        0x23 => "\x00\x23",   0x24 => "\x22\x03",   0x25 => "\x00\x25",
        0x26 => "\x00\x26",   0x27 => "\x22\x0b",   0x28 => "\x00\x28",
        0x29 => "\x00\x29",   0x2A => "\x22\x17",   0x2B => "\x00\x2b",
        0x2C => "\x00\x2c",   0x2D => "\x22\x12",   0x2E => "\x00\x2e",
        0x2F => "\x00\x2f",   0x30 => "\x00\x30",   0x31 => "\x00\x31",
        0x32 => "\x00\x32",   0x33 => "\x00\x33",   0x34 => "\x00\x34",
        0x35 => "\x00\x35",   0x36 => "\x00\x36",   0x37 => "\x00\x37",
        0x38 => "\x00\x38",   0x39 => "\x00\x39",   0x3A => "\x00\x3a",
        0x3B => "\x00\x3b",   0x3C => "\x00\x3c",   0x3D => "\x00\x3d",
        0x3E => "\x00\x3e",   0x3F => "\x00\x3f",   0x40 => "\x22\x45",
        0x41 => "\x03\x91",   0x42 => "\x03\x92",   0x43 => "\x03\xa7",
        0x44 => "\x22\x06",   0x45 => "\x03\x95",   0x46 => "\x03\xa6",
        0x47 => "\x03\x93",   0x48 => "\x03\x97",   0x49 => "\x03\x99",
        0x4A => "\x03\xd1",   0x4B => "\x03\x9a",   0x4C => "\x03\x9b",
        0x4D => "\x03\x9c",   0x4E => "\x03\x9d",   0x4F => "\x03\x9f",
        0x50 => "\x03\xa0",   0x51 => "\x03\x98",   0x52 => "\x03\xa1",
        0x53 => "\x03\xa3",   0x54 => "\x03\xa4",   0x55 => "\x03\xa5",
        0x56 => "\x03\xc2",   0x57 => "\x21\x26",   0x58 => "\x03\x9e",
        0x59 => "\x03\xa8",   0x5A => "\x03\x96",   0x5B => "\x00\x5b",
        0x5C => "\x22\x34",   0x5D => "\x00\x5d",   0x5E => "\x22\xa5",
        0x5F => "\x00\x5f",   0x60 => "\xf8\xe5",   0x61 => "\x03\xb1",
        0x62 => "\x03\xb2",   0x63 => "\x03\xc7",   0x64 => "\x03\xb4",
        0x65 => "\x03\xb5",   0x66 => "\x03\xc6",   0x67 => "\x03\xb3",
        0x68 => "\x03\xb7",   0x69 => "\x03\xb9",   0x6A => "\x03\xd5",
        0x6B => "\x03\xba",   0x6C => "\x03\xbb",   0x6D => "\x00\xb5",
        0x6E => "\x03\xbd",   0x6F => "\x03\xbf",   0x70 => "\x03\xc0",
        0x71 => "\x03\xb8",   0x72 => "\x03\xc1",   0x73 => "\x03\xc3",
        0x74 => "\x03\xc4",   0x75 => "\x03\xc5",   0x76 => "\x03\xd6",
        0x77 => "\x03\xc9",   0x78 => "\x03\xbe",   0x79 => "\x03\xc8",
        0x7A => "\x03\xb6",   0x7B => "\x00\x7b",   0x7C => "\x00\x7c",
        0x7D => "\x00\x7d",   0x7E => "\x22\x3c",   0xA0 => "\x20\xac",
        0xA1 => "\x03\xd2",   0xA2 => "\x20\x32",   0xA3 => "\x22\x64",
        0xA4 => "\x20\x44",   0xA5 => "\x22\x1e",   0xA6 => "\x01\x92",
        0xA7 => "\x26\x63",   0xA8 => "\x26\x66",   0xA9 => "\x26\x65",
        0xAA => "\x26\x60",   0xAB => "\x21\x94",   0xAC => "\x21\x90",
        0xAD => "\x21\x91",   0xAE => "\x21\x92",   0xAF => "\x21\x93",
        0xB0 => "\x00\xb0",   0xB1 => "\x00\xb1",   0xB2 => "\x20\x33",
        0xB3 => "\x22\x65",   0xB4 => "\x00\xd7",   0xB5 => "\x22\x1d",
        0xB6 => "\x22\x02",   0xB7 => "\x20\x22",   0xB8 => "\x00\xf7",
        0xB9 => "\x22\x60",   0xBA => "\x22\x61",   0xBB => "\x22\x48",
        0xBC => "\x20\x26",   0xBD => "\xf8\xe6",   0xBE => "\xf8\xe7",
        0xBF => "\x21\xb5",   0xC0 => "\x21\x35",   0xC1 => "\x21\x11",
        0xC2 => "\x21\x1c",   0xC3 => "\x21\x18",   0xC4 => "\x22\x97",
        0xC5 => "\x22\x95",   0xC6 => "\x22\x05",   0xC7 => "\x22\x29",
        0xC8 => "\x22\x2a",   0xC9 => "\x22\x83",   0xCA => "\x22\x87",
        0xCB => "\x22\x84",   0xCC => "\x22\x82",   0xCD => "\x22\x86",
        0xCE => "\x22\x08",   0xCF => "\x22\x09",   0xD0 => "\x22\x20",
        0xD1 => "\x22\x07",   0xD2 => "\xf6\xda",   0xD3 => "\xf6\xd9",
        0xD4 => "\xf6\xdb",   0xD5 => "\x22\x0f",   0xD6 => "\x22\x1a",
        0xD7 => "\x22\xc5",   0xD8 => "\x00\xac",   0xD9 => "\x22\x27",
        0xDA => "\x22\x28",   0xDB => "\x21\xd4",   0xDC => "\x21\xd0",
        0xDD => "\x21\xd1",   0xDE => "\x21\xd2",   0xDF => "\x21\xd3",
        0xE0 => "\x25\xca",   0xE1 => "\x23\x29",   0xE2 => "\xf8\xe8",
        0xE3 => "\xf8\xe9",   0xE4 => "\xf8\xea",   0xE5 => "\x22\x11",
        0xE6 => "\xf8\xeb",   0xE7 => "\xf8\xec",   0xE8 => "\xf8\xed",
        0xE9 => "\xf8\xee",   0xEA => "\xf8\xef",   0xEB => "\xf8\xf0",
        0xEC => "\xf8\xf1",   0xED => "\xf8\xf2",   0xEE => "\xf8\xf3",
        0xEF => "\xf8\xf4",   0xF1 => "\x23\x2a",   0xF2 => "\x22\x2b",
        0xF3 => "\x23\x20",   0xF4 => "\xf8\xf5",   0xF5 => "\x23\x21",
        0xF6 => "\xf8\xf6",   0xF7 => "\xf8\xf7",   0xF8 => "\xf8\xf8",
        0xF9 => "\xf8\xf9",   0xFA => "\xf8\xfa",   0xFB => "\xf8\xfb",
        0xFC => "\xf8\xfc",   0xFD => "\xf8\xfd",   0xFE => "\xf8\xfe",
    ];

    /**** Public Interface ****/

    /* Object Lifecycle */

    /**
     * Object constructor
     */
    public function __construct()
    {
        parent::__construct();

        /* Object properties */

        /* The font names are stored internally as Unicode UTF-16BE-encoded
         * strings. Since this information is static, save unnecessary trips
         * through iconv() and just use pre-encoded hexidecimal strings.
         */
        $this->_fontNames[Zend_Pdf_Font::NAME_COPYRIGHT]['en'] =
          "\x00\x43\x00\x6f\x00\x70\x00\x79\x00\x72\x00\x69\x00\x67\x00\x68\x00"
          ."\x74\x00\x20\x00\x28\x00\x63\x00\x29\x00\x20\x00\x31\x00\x39\x00"
          ."\x38\x00\x35\x00\x2c\x00\x20\x00\x31\x00\x39\x00\x38\x00\x37\x00"
          ."\x2c\x00\x20\x00\x31\x00\x39\x00\x38\x00\x39\x00\x2c\x00\x20\x00"
          ."\x31\x00\x39\x00\x39\x00\x30\x00\x2c\x00\x20\x00\x31\x00\x39\x00"
          ."\x39\x00\x37\x00\x20\x00\x41\x00\x64\x00\x6f\x00\x62\x00\x65\x00"
          ."\x20\x00\x53\x00\x79\x00\x73\x00\x74\x00\x65\x00\x6d\x00\x73\x00"
          ."\x20\x00\x49\x00\x6e\x00\x63\x00\x6f\x00\x72\x00\x70\x00\x6f\x00"
          ."\x72\x00\x61\x00\x74\x00\x65\x00\x64\x00\x2e\x00\x20\x00\x41\x00"
          ."\x6c\x00\x6c\x00\x20\x00\x72\x00\x69\x00\x67\x00\x68\x00\x74\x00"
          ."\x73\x00\x20\x00\x72\x00\x65\x00\x73\x00\x65\x00\x72\x00\x76\x00"
          ."\x65\x00\x64\x00\x2e";
        $this->_fontNames[Zend_Pdf_Font::NAME_FAMILY]['en'] =
          "\x00\x53\x00\x79\x00\x6d\x00\x62\x00\x6f\x00\x6c";
        $this->_fontNames[Zend_Pdf_Font::NAME_STYLE]['en'] =
          "\x00\x4d\x00\x65\x00\x64\x00\x69\x00\x75\x00\x6d";
        $this->_fontNames[Zend_Pdf_Font::NAME_ID]['en'] =
          "\x00\x34\x00\x33\x00\x30\x00\x36\x00\x34";
        $this->_fontNames[Zend_Pdf_Font::NAME_FULL]['en'] =
          "\x00\x53\x00\x79\x00\x6d\x00\x62\x00\x6f\x00\x6c\x00\x20\x00\x4d\x00"
          ."\x65\x00\x64\x00\x69\x00\x75\x00\x6d";
        $this->_fontNames[Zend_Pdf_Font::NAME_VERSION]['en'] =
          "\x00\x30\x00\x30\x00\x31\x00\x2e\x00\x30\x00\x30\x00\x38";
        $this->_fontNames[Zend_Pdf_Font::NAME_POSTSCRIPT]['en'] =
          "\x00\x53\x00\x79\x00\x6d\x00\x62\x00\x6f\x00\x6c";

        $this->_isBold = false;
        $this->_isItalic = false;
        $this->_isMonospaced = false;

        $this->_underlinePosition = -100;
        $this->_underlineThickness = 50;
        $this->_strikePosition = 225;
        $this->_strikeThickness = 50;

        $this->_unitsPerEm = 1000;

        $this->_ascent = 1000;
        $this->_descent = 0;
        $this->_lineGap = 200;

        /* The glyph numbers assigned here are synthetic; they do not match the
         * actual glyph numbers used by the font. This is not a big deal though
         * since this data never makes it to the PDF file. It is only used
         * internally for layout calculations.
         */
        $this->_glyphWidths = [
            0x00 => 0x01F4,   0x01 => 0xFA,   0x02 => 0x014D,   0x03 => 0x02C9,
            0x04 => 0x01F4,   0x05 => 0x0225,   0x06 => 0x0341,   0x07 => 0x030A,
            0x08 => 0x01B7,   0x09 => 0x014D,   0x0A => 0x014D,   0x0B => 0x01F4,
            0x0C => 0x0225,   0x0D => 0xFA,   0x0E => 0x0225,   0x0F => 0xFA,
            0x10 => 0x0116,   0x11 => 0x01F4,   0x12 => 0x01F4,   0x13 => 0x01F4,
            0x14 => 0x01F4,   0x15 => 0x01F4,   0x16 => 0x01F4,   0x17 => 0x01F4,
            0x18 => 0x01F4,   0x19 => 0x01F4,   0x1A => 0x01F4,   0x1B => 0x0116,
            0x1C => 0x0116,   0x1D => 0x0225,   0x1E => 0x0225,   0x1F => 0x0225,
            0x20 => 0x01BC,   0x21 => 0x0225,   0x22 => 0x02D2,   0x23 => 0x029B,
            0x24 => 0x02D2,   0x25 => 0x0264,   0x26 => 0x0263,   0x27 => 0x02FB,
            0x28 => 0x025B,   0x29 => 0x02D2,   0x2A => 0x014D,   0x2B => 0x0277,
            0x2C => 0x02D2,   0x2D => 0x02AE,   0x2E => 0x0379,   0x2F => 0x02D2,
            0x30 => 0x02D2,   0x31 => 0x0300,   0x32 => 0x02E5,   0x33 => 0x022C,
            0x34 => 0x0250,   0x35 => 0x0263,   0x36 => 0x02B2,   0x37 => 0x01B7,
            0x38 => 0x0300,   0x39 => 0x0285,   0x3A => 0x031B,   0x3B => 0x0263,
            0x3C => 0x014D,   0x3D => 0x035F,   0x3E => 0x014D,   0x3F => 0x0292,
            0x40 => 0x01F4,   0x41 => 0x01F4,   0x42 => 0x0277,   0x43 => 0x0225,
            0x44 => 0x0225,   0x45 => 0x01EE,   0x46 => 0x01B7,   0x47 => 0x0209,
            0x48 => 0x019B,   0x49 => 0x025B,   0x4A => 0x0149,   0x4B => 0x025B,
            0x4C => 0x0225,   0x4D => 0x0225,   0x4E => 0x0240,   0x4F => 0x0209,
            0x50 => 0x0225,   0x51 => 0x0225,   0x52 => 0x0209,   0x53 => 0x0225,
            0x54 => 0x025B,   0x55 => 0x01B7,   0x56 => 0x0240,   0x57 => 0x02C9,
            0x58 => 0x02AE,   0x59 => 0x01ED,   0x5A => 0x02AE,   0x5B => 0x01EE,
            0x5C => 0x01E0,   0x5D => 0xC8,   0x5E => 0x01E0,   0x5F => 0x0225,
            0x60 => 0x02EE,   0x61 => 0x026C,   0x62 => 0xF7,   0x63 => 0x0225,
            0x64 => 0xA7,   0x65 => 0x02C9,   0x66 => 0x01F4,   0x67 => 0x02F1,
            0x68 => 0x02F1,   0x69 => 0x02F1,   0x6A => 0x02F1,   0x6B => 0x0412,
            0x6C => 0x03DB,   0x6D => 0x025B,   0x6E => 0x03DB,   0x6F => 0x025B,
            0x70 => 0x0190,   0x71 => 0x0225,   0x72 => 0x019B,   0x73 => 0x0225,
            0x74 => 0x0225,   0x75 => 0x02C9,   0x76 => 0x01EE,   0x77 => 0x01CC,
            0x78 => 0x0225,   0x79 => 0x0225,   0x7A => 0x0225,   0x7B => 0x0225,
            0x7C => 0x03E8,   0x7D => 0x025B,   0x7E => 0x03E8,   0x7F => 0x0292,
            0x80 => 0x0337,   0x81 => 0x02AE,   0x82 => 0x031B,   0x83 => 0x03DB,
            0x84 => 0x0300,   0x85 => 0x0300,   0x86 => 0x0337,   0x87 => 0x0300,
            0x88 => 0x0300,   0x89 => 0x02C9,   0x8A => 0x02C9,   0x8B => 0x02C9,
            0x8C => 0x02C9,   0x8D => 0x02C9,   0x8E => 0x02C9,   0x8F => 0x02C9,
            0x90 => 0x0300,   0x91 => 0x02C9,   0x92 => 0x0316,   0x93 => 0x0316,
            0x94 => 0x037A,   0x95 => 0x0337,   0x96 => 0x0225,   0x97 => 0xFA,
            0x98 => 0x02C9,   0x99 => 0x025B,   0x9A => 0x025B,   0x9B => 0x0412,
            0x9C => 0x03DB,   0x9D => 0x025B,   0x9E => 0x03DB,   0x9F => 0x025B,
            0xA0 => 0x01EE,   0xA1 => 0x0149,   0xA2 => 0x0316,   0xA3 => 0x0316,
            0xA4 => 0x0312,   0xA5 => 0x02C9,   0xA6 => 0x0180,   0xA7 => 0x0180,
            0xA8 => 0x0180,   0xA9 => 0x0180,   0xAA => 0x0180,   0xAB => 0x0180,
            0xAC => 0x01EE,   0xAD => 0x01EE,   0xAE => 0x01EE,   0xAF => 0x01EE,
            0xB0 => 0x0149,   0xB1 => 0x0112,   0xB2 => 0x02AE,   0xB3 => 0x02AE,
            0xB4 => 0x02AE,   0xB5 => 0x0180,   0xB6 => 0x0180,   0xB7 => 0x0180,
            0xB8 => 0x0180,   0xB9 => 0x0180,   0xBA => 0x0180,   0xBB => 0x01EE,
            0xBC => 0x01EE,   0xBD => 0x01EE,   0xBE => 0x0316];

        /* The cmap table is similarly synthesized.
         */
        $cmapData = [
            0x20 => 0x01,   0x21 => 0x02, 0x2200 => 0x03,   0x23 => 0x04,
            0x2203 => 0x05,   0x25 => 0x06,   0x26 => 0x07, 0x220B => 0x08,
            0x28 => 0x09,   0x29 => 0x0A, 0x2217 => 0x0B,   0x2B => 0x0C,
            0x2C => 0x0D, 0x2212 => 0x0E,   0x2E => 0x0F,   0x2F => 0x10,
            0x30 => 0x11,   0x31 => 0x12,   0x32 => 0x13,   0x33 => 0x14,
            0x34 => 0x15,   0x35 => 0x16,   0x36 => 0x17,   0x37 => 0x18,
            0x38 => 0x19,   0x39 => 0x1A,   0x3A => 0x1B,   0x3B => 0x1C,
            0x3C => 0x1D,   0x3D => 0x1E,   0x3E => 0x1F,   0x3F => 0x20,
            0x2245 => 0x21, 0x0391 => 0x22, 0x0392 => 0x23, 0x03A7 => 0x24,
            0x2206 => 0x25, 0x0395 => 0x26, 0x03A6 => 0x27, 0x0393 => 0x28,
            0x0397 => 0x29, 0x0399 => 0x2A, 0x03D1 => 0x2B, 0x039A => 0x2C,
            0x039B => 0x2D, 0x039C => 0x2E, 0x039D => 0x2F, 0x039F => 0x30,
            0x03A0 => 0x31, 0x0398 => 0x32, 0x03A1 => 0x33, 0x03A3 => 0x34,
            0x03A4 => 0x35, 0x03A5 => 0x36, 0x03C2 => 0x37, 0x2126 => 0x38,
            0x039E => 0x39, 0x03A8 => 0x3A, 0x0396 => 0x3B,   0x5B => 0x3C,
            0x2234 => 0x3D,   0x5D => 0x3E, 0x22A5 => 0x3F,   0x5F => 0x40,
            0xF8E5 => 0x41, 0x03B1 => 0x42, 0x03B2 => 0x43, 0x03C7 => 0x44,
            0x03B4 => 0x45, 0x03B5 => 0x46, 0x03C6 => 0x47, 0x03B3 => 0x48,
            0x03B7 => 0x49, 0x03B9 => 0x4A, 0x03D5 => 0x4B, 0x03BA => 0x4C,
            0x03BB => 0x4D,   0xB5 => 0x4E, 0x03BD => 0x4F, 0x03BF => 0x50,
            0x03C0 => 0x51, 0x03B8 => 0x52, 0x03C1 => 0x53, 0x03C3 => 0x54,
            0x03C4 => 0x55, 0x03C5 => 0x56, 0x03D6 => 0x57, 0x03C9 => 0x58,
            0x03BE => 0x59, 0x03C8 => 0x5A, 0x03B6 => 0x5B,   0x7B => 0x5C,
            0x7C => 0x5D,   0x7D => 0x5E, 0x223C => 0x5F, 0x20AC => 0x60,
            0x03D2 => 0x61, 0x2032 => 0x62, 0x2264 => 0x63, 0x2044 => 0x64,
            0x221E => 0x65, 0x0192 => 0x66, 0x2663 => 0x67, 0x2666 => 0x68,
            0x2665 => 0x69, 0x2660 => 0x6A, 0x2194 => 0x6B, 0x2190 => 0x6C,
            0x2191 => 0x6D, 0x2192 => 0x6E, 0x2193 => 0x6F,   0xB0 => 0x70,
            0xB1 => 0x71, 0x2033 => 0x72, 0x2265 => 0x73,   0xD7 => 0x74,
            0x221D => 0x75, 0x2202 => 0x76, 0x2022 => 0x77,   0xF7 => 0x78,
            0x2260 => 0x79, 0x2261 => 0x7A, 0x2248 => 0x7B, 0x2026 => 0x7C,
            0xF8E6 => 0x7D, 0xF8E7 => 0x7E, 0x21B5 => 0x7F, 0x2135 => 0x80,
            0x2111 => 0x81, 0x211C => 0x82, 0x2118 => 0x83, 0x2297 => 0x84,
            0x2295 => 0x85, 0x2205 => 0x86, 0x2229 => 0x87, 0x222A => 0x88,
            0x2283 => 0x89, 0x2287 => 0x8A, 0x2284 => 0x8B, 0x2282 => 0x8C,
            0x2286 => 0x8D, 0x2208 => 0x8E, 0x2209 => 0x8F, 0x2220 => 0x90,
            0x2207 => 0x91, 0xF6DA => 0x92, 0xF6D9 => 0x93, 0xF6DB => 0x94,
            0x220F => 0x95, 0x221A => 0x96, 0x22C5 => 0x97,   0xAC => 0x98,
            0x2227 => 0x99, 0x2228 => 0x9A, 0x21D4 => 0x9B, 0x21D0 => 0x9C,
            0x21D1 => 0x9D, 0x21D2 => 0x9E, 0x21D3 => 0x9F, 0x25CA => 0xA0,
            0x2329 => 0xA1, 0xF8E8 => 0xA2, 0xF8E9 => 0xA3, 0xF8EA => 0xA4,
            0x2211 => 0xA5, 0xF8EB => 0xA6, 0xF8EC => 0xA7, 0xF8ED => 0xA8,
            0xF8EE => 0xA9, 0xF8EF => 0xAA, 0xF8F0 => 0xAB, 0xF8F1 => 0xAC,
            0xF8F2 => 0xAD, 0xF8F3 => 0xAE, 0xF8F4 => 0xAF, 0x232A => 0xB0,
            0x222B => 0xB1, 0x2320 => 0xB2, 0xF8F5 => 0xB3, 0x2321 => 0xB4,
            0xF8F6 => 0xB5, 0xF8F7 => 0xB6, 0xF8F8 => 0xB7, 0xF8F9 => 0xB8,
            0xF8FA => 0xB9, 0xF8FB => 0xBA, 0xF8FC => 0xBB, 0xF8FD => 0xBC,
            0xF8FE => 0xBD, 0xF8FF => 0xBE];
        require_once 'Zend/Pdf/Cmap.php';
        $this->_cmap = Zend_Pdf_Cmap::cmapWithTypeData(
            Zend_Pdf_Cmap::TYPE_BYTE_ENCODING_STATIC, $cmapData);

        /* Resource dictionary */

        /* The resource dictionary for the standard fonts is sparse because PDF
         * viewers already have all of the metrics data. We only need to provide
         * the font name and encoding method.
         */
        $this->_resource->BaseFont = new Zend_Pdf_Element_Name('Symbol');

        /* This font has a built-in custom character encoding method. Don't
         * override with WinAnsi like the other built-in fonts or else it will
         * not work as expected.
         */
        $this->_resource->Encoding = null;
    }

    /* Information and Conversion Methods */

    /**
     * Convert string encoding from local encoding to font encoding. Overridden
     * to defeat the conversion behavior for this ornamental font.
     *
     * @param  string  $string
     * @param  string  $charEncoding  Character encoding of source text.
     * @return string
     */
    public function encodeString($string, $charEncoding)
    {
        /* This isn't the optimal time to perform this conversion, but it must
         * live here until the remainder of the layout code is completed. This,
         * and the $charEncoding parameter, will go away soon...
         */
        if ($charEncoding != 'UTF-16BE') {
            $string = iconv($charEncoding, 'UTF-16BE', $string);
        }
        /**
         * @todo Properly handle characters encoded as surrogate pairs.
         */
        $encodedString = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $characterCode = (ord($string[$i++]) << 8) | ord($string[$i]);
            if (isset($this->_toFontEncoding[$characterCode])) {
                $encodedString .= $this->_toFontEncoding[$characterCode];
            }
            /* For now, mimic the behavior in Zend_Pdf_Font::encodeString()
             * where unknown characters are removed completely. This is not
             * perfect, but we should be consistent. In a future revision,
             * we will use the well-known substitution character 0x1a
             * (Control-Z).
             */

        }

        return $encodedString;
    }

    /**
     * Convert string encoding from font encoding to local encoding. Overridden
     * to defeat the conversion behavior for this ornamental font.
     *
     * @param  string  $string
     * @param  string  $charEncoding  Character encoding of resulting text.
     * @return string
     */
    public function decodeString($string, $charEncoding)
    {
        $decodedString = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $characterCode = ord($string[$i]);
            if (isset($this->_fromFontEncoding[$characterCode])) {
                $decodedString .= $this->_fromFontEncoding[$characterCode];
            }
            /* For now, mimic the behavior in Zend_Pdf_Font::encodeString()
             * where unknown characters are removed completely. This is not
             * perfect, but we should be consistent. In a future revision,
             * we will use the Unicode substitution character (U+FFFD).
             */

        }
        if ($charEncoding != 'UTF-16BE') {
            $decodedString = iconv('UTF-16BE', $charEncoding, $decodedString);
        }

        return $decodedString;
    }

    /**
     * Converts a Latin-encoded string that fakes the font's internal encoding
     * to the proper Unicode characters, in UTF-16BE encoding.
     *
     * Used to maintain backwards compatibility with the 20 year-old legacy
     * method of using this font, which is still employed by recent versions of
     * some popular word processors.
     *
     * Note that using this method adds overhead due to the additional
     * character conversion. Don't use this for new code; it is more efficient
     * to use the appropriate Unicode characters directly.
     *
     * @param  string  $string
     * @param  string  $charEncoding  (optional) Character encoding of source
     *                                string. Defaults to current locale.
     * @return string
     */
    public function toUnicode($string, $charEncoding = '')
    {
        /* When using these faked strings, the closest match to the font's
         * internal encoding is ISO-8859-1.
         */
        if ($charEncoding != 'ISO-8859-1') {
            $string = iconv($charEncoding, 'ISO-8859-1', $string);
        }

        return $this->decodeString($string, 'UTF-16BE');
    }
}
