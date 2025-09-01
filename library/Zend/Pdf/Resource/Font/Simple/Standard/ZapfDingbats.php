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
 * Implementation for the standard PDF font ZapfDingbats.
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
class Zend_Pdf_Resource_Font_Simple_Standard_ZapfDingbats extends Zend_Pdf_Resource_Font_Simple_Standard
{
    /**** Instance Variables ****/

    /**
     * Array for conversion from local encoding to special font encoding.
     * See {@link encodeString()}.
     *
     * @var array
     */
    protected $_toFontEncoding = [
        0x20 => "\x20", 0x2701 => "\x21", 0x2702 => "\x22", 0x2703 => "\x23",
        0x2704 => "\x24", 0x260E => "\x25", 0x2706 => "\x26", 0x2707 => "\x27",
        0x2708 => "\x28", 0x2709 => "\x29", 0x261B => "\x2a", 0x261E => "\x2b",
        0x270C => "\x2c", 0x270D => "\x2d", 0x270E => "\x2e", 0x270F => "\x2f",
        0x2710 => "\x30", 0x2711 => "\x31", 0x2712 => "\x32", 0x2713 => "\x33",
        0x2714 => "\x34", 0x2715 => "\x35", 0x2716 => "\x36", 0x2717 => "\x37",
        0x2718 => "\x38", 0x2719 => "\x39", 0x271A => "\x3a", 0x271B => "\x3b",
        0x271C => "\x3c", 0x271D => "\x3d", 0x271E => "\x3e", 0x271F => "\x3f",
        0x2720 => "\x40", 0x2721 => "\x41", 0x2722 => "\x42", 0x2723 => "\x43",
        0x2724 => "\x44", 0x2725 => "\x45", 0x2726 => "\x46", 0x2727 => "\x47",
        0x2605 => "\x48", 0x2729 => "\x49", 0x272A => "\x4a", 0x272B => "\x4b",
        0x272C => "\x4c", 0x272D => "\x4d", 0x272E => "\x4e", 0x272F => "\x4f",
        0x2730 => "\x50", 0x2731 => "\x51", 0x2732 => "\x52", 0x2733 => "\x53",
        0x2734 => "\x54", 0x2735 => "\x55", 0x2736 => "\x56", 0x2737 => "\x57",
        0x2738 => "\x58", 0x2739 => "\x59", 0x273A => "\x5a", 0x273B => "\x5b",
        0x273C => "\x5c", 0x273D => "\x5d", 0x273E => "\x5e", 0x273F => "\x5f",
        0x2740 => "\x60", 0x2741 => "\x61", 0x2742 => "\x62", 0x2743 => "\x63",
        0x2744 => "\x64", 0x2745 => "\x65", 0x2746 => "\x66", 0x2747 => "\x67",
        0x2748 => "\x68", 0x2749 => "\x69", 0x274A => "\x6a", 0x274B => "\x6b",
        0x25CF => "\x6c", 0x274D => "\x6d", 0x25A0 => "\x6e", 0x274F => "\x6f",
        0x2750 => "\x70", 0x2751 => "\x71", 0x2752 => "\x72", 0x25B2 => "\x73",
        0x25BC => "\x74", 0x25C6 => "\x75", 0x2756 => "\x76", 0x25D7 => "\x77",
        0x2758 => "\x78", 0x2759 => "\x79", 0x275A => "\x7a", 0x275B => "\x7b",
        0x275C => "\x7c", 0x275D => "\x7d", 0x275E => "\x7e", 0x2768 => "\x80",
        0x2769 => "\x81", 0x276A => "\x82", 0x276B => "\x83", 0x276C => "\x84",
        0x276D => "\x85", 0x276E => "\x86", 0x276F => "\x87", 0x2770 => "\x88",
        0x2771 => "\x89", 0x2772 => "\x8a", 0x2773 => "\x8b", 0x2774 => "\x8c",
        0x2775 => "\x8d", 0x2761 => "\xa1", 0x2762 => "\xa2", 0x2763 => "\xa3",
        0x2764 => "\xa4", 0x2765 => "\xa5", 0x2766 => "\xa6", 0x2767 => "\xa7",
        0x2663 => "\xa8", 0x2666 => "\xa9", 0x2665 => "\xaa", 0x2660 => "\xab",
        0x2460 => "\xac", 0x2461 => "\xad", 0x2462 => "\xae", 0x2463 => "\xaf",
        0x2464 => "\xb0", 0x2465 => "\xb1", 0x2466 => "\xb2", 0x2467 => "\xb3",
        0x2468 => "\xb4", 0x2469 => "\xb5", 0x2776 => "\xb6", 0x2777 => "\xb7",
        0x2778 => "\xb8", 0x2779 => "\xb9", 0x277A => "\xba", 0x277B => "\xbb",
        0x277C => "\xbc", 0x277D => "\xbd", 0x277E => "\xbe", 0x277F => "\xbf",
        0x2780 => "\xc0", 0x2781 => "\xc1", 0x2782 => "\xc2", 0x2783 => "\xc3",
        0x2784 => "\xc4", 0x2785 => "\xc5", 0x2786 => "\xc6", 0x2787 => "\xc7",
        0x2788 => "\xc8", 0x2789 => "\xc9", 0x278A => "\xca", 0x278B => "\xcb",
        0x278C => "\xcc", 0x278D => "\xcd", 0x278E => "\xce", 0x278F => "\xcf",
        0x2790 => "\xd0", 0x2791 => "\xd1", 0x2792 => "\xd2", 0x2793 => "\xd3",
        0x2794 => "\xd4", 0x2192 => "\xd5", 0x2194 => "\xd6", 0x2195 => "\xd7",
        0x2798 => "\xd8", 0x2799 => "\xd9", 0x279A => "\xda", 0x279B => "\xdb",
        0x279C => "\xdc", 0x279D => "\xdd", 0x279E => "\xde", 0x279F => "\xdf",
        0x27A0 => "\xe0", 0x27A1 => "\xe1", 0x27A2 => "\xe2", 0x27A3 => "\xe3",
        0x27A4 => "\xe4", 0x27A5 => "\xe5", 0x27A6 => "\xe6", 0x27A7 => "\xe7",
        0x27A8 => "\xe8", 0x27A9 => "\xe9", 0x27AA => "\xea", 0x27AB => "\xeb",
        0x27AC => "\xec", 0x27AD => "\xed", 0x27AE => "\xee", 0x27AF => "\xef",
        0x27B1 => "\xf1", 0x27B2 => "\xf2", 0x27B3 => "\xf3", 0x27B4 => "\xf4",
        0x27B5 => "\xf5", 0x27B6 => "\xf6", 0x27B7 => "\xf7", 0x27B8 => "\xf8",
        0x27B9 => "\xf9", 0x27BA => "\xfa", 0x27BB => "\xfb", 0x27BC => "\xfc",
        0x27BD => "\xfd", 0x27BE => "\xfe"];

    /**
     * Array for conversion from special font encoding to local encoding.
     * See {@link decodeString()}.
     *
     * @var array
     */
    protected $_fromFontEncoding = [
        0x20 => "\x00\x20",   0x21 => "\x27\x01",   0x22 => "\x27\x02",
        0x23 => "\x27\x03",   0x24 => "\x27\x04",   0x25 => "\x26\x0e",
        0x26 => "\x27\x06",   0x27 => "\x27\x07",   0x28 => "\x27\x08",
        0x29 => "\x27\x09",   0x2A => "\x26\x1b",   0x2B => "\x26\x1e",
        0x2C => "\x27\x0c",   0x2D => "\x27\x0d",   0x2E => "\x27\x0e",
        0x2F => "\x27\x0f",   0x30 => "\x27\x10",   0x31 => "\x27\x11",
        0x32 => "\x27\x12",   0x33 => "\x27\x13",   0x34 => "\x27\x14",
        0x35 => "\x27\x15",   0x36 => "\x27\x16",   0x37 => "\x27\x17",
        0x38 => "\x27\x18",   0x39 => "\x27\x19",   0x3A => "\x27\x1a",
        0x3B => "\x27\x1b",   0x3C => "\x27\x1c",   0x3D => "\x27\x1d",
        0x3E => "\x27\x1e",   0x3F => "\x27\x1f",   0x40 => "\x27\x20",
        0x41 => "\x27\x21",   0x42 => "\x27\x22",   0x43 => "\x27\x23",
        0x44 => "\x27\x24",   0x45 => "\x27\x25",   0x46 => "\x27\x26",
        0x47 => "\x27\x27",   0x48 => "\x26\x05",   0x49 => "\x27\x29",
        0x4A => "\x27\x2a",   0x4B => "\x27\x2b",   0x4C => "\x27\x2c",
        0x4D => "\x27\x2d",   0x4E => "\x27\x2e",   0x4F => "\x27\x2f",
        0x50 => "\x27\x30",   0x51 => "\x27\x31",   0x52 => "\x27\x32",
        0x53 => "\x27\x33",   0x54 => "\x27\x34",   0x55 => "\x27\x35",
        0x56 => "\x27\x36",   0x57 => "\x27\x37",   0x58 => "\x27\x38",
        0x59 => "\x27\x39",   0x5A => "\x27\x3a",   0x5B => "\x27\x3b",
        0x5C => "\x27\x3c",   0x5D => "\x27\x3d",   0x5E => "\x27\x3e",
        0x5F => "\x27\x3f",   0x60 => "\x27\x40",   0x61 => "\x27\x41",
        0x62 => "\x27\x42",   0x63 => "\x27\x43",   0x64 => "\x27\x44",
        0x65 => "\x27\x45",   0x66 => "\x27\x46",   0x67 => "\x27\x47",
        0x68 => "\x27\x48",   0x69 => "\x27\x49",   0x6A => "\x27\x4a",
        0x6B => "\x27\x4b",   0x6C => "\x25\xcf",   0x6D => "\x27\x4d",
        0x6E => "\x25\xa0",   0x6F => "\x27\x4f",   0x70 => "\x27\x50",
        0x71 => "\x27\x51",   0x72 => "\x27\x52",   0x73 => "\x25\xb2",
        0x74 => "\x25\xbc",   0x75 => "\x25\xc6",   0x76 => "\x27\x56",
        0x77 => "\x25\xd7",   0x78 => "\x27\x58",   0x79 => "\x27\x59",
        0x7A => "\x27\x5a",   0x7B => "\x27\x5b",   0x7C => "\x27\x5c",
        0x7D => "\x27\x5d",   0x7E => "\x27\x5e",   0x80 => "\x27\x68",
        0x81 => "\x27\x69",   0x82 => "\x27\x6a",   0x83 => "\x27\x6b",
        0x84 => "\x27\x6c",   0x85 => "\x27\x6d",   0x86 => "\x27\x6e",
        0x87 => "\x27\x6f",   0x88 => "\x27\x70",   0x89 => "\x27\x71",
        0x8A => "\x27\x72",   0x8B => "\x27\x73",   0x8C => "\x27\x74",
        0x8D => "\x27\x75",   0xA1 => "\x27\x61",   0xA2 => "\x27\x62",
        0xA3 => "\x27\x63",   0xA4 => "\x27\x64",   0xA5 => "\x27\x65",
        0xA6 => "\x27\x66",   0xA7 => "\x27\x67",   0xA8 => "\x26\x63",
        0xA9 => "\x26\x66",   0xAA => "\x26\x65",   0xAB => "\x26\x60",
        0xAC => "\x24\x60",   0xAD => "\x24\x61",   0xAE => "\x24\x62",
        0xAF => "\x24\x63",   0xB0 => "\x24\x64",   0xB1 => "\x24\x65",
        0xB2 => "\x24\x66",   0xB3 => "\x24\x67",   0xB4 => "\x24\x68",
        0xB5 => "\x24\x69",   0xB6 => "\x27\x76",   0xB7 => "\x27\x77",
        0xB8 => "\x27\x78",   0xB9 => "\x27\x79",   0xBA => "\x27\x7a",
        0xBB => "\x27\x7b",   0xBC => "\x27\x7c",   0xBD => "\x27\x7d",
        0xBE => "\x27\x7e",   0xBF => "\x27\x7f",   0xC0 => "\x27\x80",
        0xC1 => "\x27\x81",   0xC2 => "\x27\x82",   0xC3 => "\x27\x83",
        0xC4 => "\x27\x84",   0xC5 => "\x27\x85",   0xC6 => "\x27\x86",
        0xC7 => "\x27\x87",   0xC8 => "\x27\x88",   0xC9 => "\x27\x89",
        0xCA => "\x27\x8a",   0xCB => "\x27\x8b",   0xCC => "\x27\x8c",
        0xCD => "\x27\x8d",   0xCE => "\x27\x8e",   0xCF => "\x27\x8f",
        0xD0 => "\x27\x90",   0xD1 => "\x27\x91",   0xD2 => "\x27\x92",
        0xD3 => "\x27\x93",   0xD4 => "\x27\x94",   0xD5 => "\x21\x92",
        0xD6 => "\x21\x94",   0xD7 => "\x21\x95",   0xD8 => "\x27\x98",
        0xD9 => "\x27\x99",   0xDA => "\x27\x9a",   0xDB => "\x27\x9b",
        0xDC => "\x27\x9c",   0xDD => "\x27\x9d",   0xDE => "\x27\x9e",
        0xDF => "\x27\x9f",   0xE0 => "\x27\xa0",   0xE1 => "\x27\xa1",
        0xE2 => "\x27\xa2",   0xE3 => "\x27\xa3",   0xE4 => "\x27\xa4",
        0xE5 => "\x27\xa5",   0xE6 => "\x27\xa6",   0xE7 => "\x27\xa7",
        0xE8 => "\x27\xa8",   0xE9 => "\x27\xa9",   0xEA => "\x27\xaa",
        0xEB => "\x27\xab",   0xEC => "\x27\xac",   0xED => "\x27\xad",
        0xEE => "\x27\xae",   0xEF => "\x27\xaf",   0xF1 => "\x27\xb1",
        0xF2 => "\x27\xb2",   0xF3 => "\x27\xb3",   0xF4 => "\x27\xb4",
        0xF5 => "\x27\xb5",   0xF6 => "\x27\xb6",   0xF7 => "\x27\xb7",
        0xF8 => "\x27\xb8",   0xF9 => "\x27\xb9",   0xFA => "\x27\xba",
        0xFB => "\x27\xbb",   0xFC => "\x27\xbc",   0xFD => "\x27\xbd",
        0xFE => "\x27\xbe"];

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
          ."\x2c\x00\x20\x00\x31\x00\x39\x00\x38\x00\x38\x00\x2c\x00\x20\x00"
          ."\x31\x00\x39\x00\x38\x00\x39\x00\x2c\x00\x20\x00\x31\x00\x39\x00"
          ."\x39\x00\x37\x00\x20\x00\x41\x00\x64\x00\x6f\x00\x62\x00\x65\x00"
          ."\x20\x00\x53\x00\x79\x00\x73\x00\x74\x00\x65\x00\x6d\x00\x73\x00"
          ."\x20\x00\x49\x00\x6e\x00\x63\x00\x6f\x00\x72\x00\x70\x00\x6f\x00"
          ."\x72\x00\x61\x00\x74\x00\x65\x00\x64\x00\x2e\x00\x20\x00\x41\x00"
          ."\x6c\x00\x6c\x00\x20\x00\x52\x00\x69\x00\x67\x00\x68\x00\x74\x00"
          ."\x73\x00\x20\x00\x52\x00\x65\x00\x73\x00\x65\x00\x72\x00\x76\x00"
          ."\x65\x00\x64\x00\x2e\x00\x49\x00\x54\x00\x43\x00\x20\x00\x5a\x00"
          ."\x61\x00\x70\x00\x66\x00\x20\x00\x44\x00\x69\x00\x6e\x00\x67\x00"
          ."\x62\x00\x61\x00\x74\x00\x73\x00\x20\x00\x69\x00\x73\x00\x20\x00"
          ."\x61\x00\x20\x00\x72\x00\x65\x00\x67\x00\x69\x00\x73\x00\x74\x00"
          ."\x65\x00\x72\x00\x65\x00\x64\x00\x20\x00\x74\x00\x72\x00\x61\x00"
          ."\x64\x00\x65\x00\x6d\x00\x61\x00\x72\x00\x6b\x00\x20\x00\x6f\x00"
          ."\x66\x00\x20\x00\x49\x00\x6e\x00\x74\x00\x65\x00\x72\x00\x6e\x00"
          ."\x61\x00\x74\x00\x69\x00\x6f\x00\x6e\x00\x61\x00\x6c\x00\x20\x00"
          ."\x54\x00\x79\x00\x70\x00\x65\x00\x66\x00\x61\x00\x63\x00\x65\x00"
          ."\x20\x00\x43\x00\x6f\x00\x72\x00\x70\x00\x6f\x00\x72\x00\x61\x00"
          ."\x74\x00\x69\x00\x6f\x00\x6e\x00\x2e";
        $this->_fontNames[Zend_Pdf_Font::NAME_FAMILY]['en'] =
          "\x00\x5a\x00\x61\x00\x70\x00\x66\x00\x44\x00\x69\x00\x6e\x00\x67\x00"
          ."\x62\x00\x61\x00\x74\x00\x73";
        $this->_fontNames[Zend_Pdf_Font::NAME_STYLE]['en'] =
          "\x00\x4d\x00\x65\x00\x64\x00\x69\x00\x75\x00\x6d";
        $this->_fontNames[Zend_Pdf_Font::NAME_ID]['en'] =
          "\x00\x34\x00\x33\x00\x30\x00\x38\x00\x32";
        $this->_fontNames[Zend_Pdf_Font::NAME_FULL]['en'] =
          "\x00\x5a\x00\x61\x00\x70\x00\x66\x00\x44\x00\x69\x00\x6e\x00\x67\x00"
          ."\x62\x00\x61\x00\x74\x00\x73\x00\x20\x00\x4d\x00\x65\x00\x64\x00"
          ."\x69\x00\x75\x00\x6d";
        $this->_fontNames[Zend_Pdf_Font::NAME_VERSION]['en'] =
          "\x00\x30\x00\x30\x00\x32\x00\x2e\x00\x30\x00\x30\x00\x30";
        $this->_fontNames[Zend_Pdf_Font::NAME_POSTSCRIPT]['en'] =
          "\x00\x5a\x00\x61\x00\x70\x00\x66\x00\x44\x00\x69\x00\x6e\x00\x67\x00"
          ."\x62\x00\x61\x00\x74\x00\x73";

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
            0x00 => 0x01F4,   0x01 => 0x0116,   0x02 => 0x03CE,   0x03 => 0x03C1,
            0x04 => 0x03CE,   0x05 => 0x03D4,   0x06 => 0x02CF,   0x07 => 0x0315,
            0x08 => 0x0316,   0x09 => 0x0317,   0x0A => 0x02B2,   0x0B => 0x03C0,
            0x0C => 0x03AB,   0x0D => 0x0225,   0x0E => 0x0357,   0x0F => 0x038F,
            0x10 => 0x03A5,   0x11 => 0x038F,   0x12 => 0x03B1,   0x13 => 0x03CE,
            0x14 => 0x02F3,   0x15 => 0x034E,   0x16 => 0x02FA,   0x17 => 0x02F9,
            0x18 => 0x023B,   0x19 => 0x02A5,   0x1A => 0x02FB,   0x1B => 0x02F8,
            0x1C => 0x02F7,   0x1D => 0x02F2,   0x1E => 0x01EE,   0x1F => 0x0228,
            0x20 => 0x0219,   0x21 => 0x0241,   0x22 => 0x02B4,   0x23 => 0x0312,
            0x24 => 0x0314,   0x25 => 0x0314,   0x26 => 0x0316,   0x27 => 0x0319,
            0x28 => 0x031A,   0x29 => 0x0330,   0x2A => 0x0337,   0x2B => 0x0315,
            0x2C => 0x0349,   0x2D => 0x0337,   0x2E => 0x0341,   0x2F => 0x0330,
            0x30 => 0x033F,   0x31 => 0x039B,   0x32 => 0x02E8,   0x33 => 0x02D3,
            0x34 => 0x02ED,   0x35 => 0x0316,   0x36 => 0x0318,   0x37 => 0x02B7,
            0x38 => 0x0308,   0x39 => 0x0300,   0x3A => 0x0318,   0x3B => 0x02F7,
            0x3C => 0x02C3,   0x3D => 0x02C4,   0x3E => 0x02AA,   0x3F => 0x02BD,
            0x40 => 0x033A,   0x41 => 0x032F,   0x42 => 0x0315,   0x43 => 0x0315,
            0x44 => 0x02C3,   0x45 => 0x02AF,   0x46 => 0x02B8,   0x47 => 0x02B1,
            0x48 => 0x0312,   0x49 => 0x0313,   0x4A => 0x02C9,   0x4B => 0x0317,
            0x4C => 0x0311,   0x4D => 0x0317,   0x4E => 0x0369,   0x4F => 0x02F9,
            0x50 => 0x02FA,   0x51 => 0x02FA,   0x52 => 0x02F7,   0x53 => 0x02F7,
            0x54 => 0x037C,   0x55 => 0x037C,   0x56 => 0x0314,   0x57 => 0x0310,
            0x58 => 0x01B6,   0x59 => 0x8A,   0x5A => 0x0115,   0x5B => 0x019F,
            0x5C => 0x0188,   0x5D => 0x0188,   0x5E => 0x029C,   0x5F => 0x029C,
            0x60 => 0x0186,   0x61 => 0x0186,   0x62 => 0x013D,   0x63 => 0x013D,
            0x64 => 0x0114,   0x65 => 0x0114,   0x66 => 0x01FD,   0x67 => 0x01FD,
            0x68 => 0x019A,   0x69 => 0x019A,   0x6A => 0xEA,   0x6B => 0xEA,
            0x6C => 0x014E,   0x6D => 0x014E,   0x6E => 0x02DC,   0x6F => 0x0220,
            0x70 => 0x0220,   0x71 => 0x038E,   0x72 => 0x029B,   0x73 => 0x02F8,
            0x74 => 0x02F8,   0x75 => 0x0308,   0x76 => 0x0253,   0x77 => 0x02B6,
            0x78 => 0x0272,   0x79 => 0x0314,   0x7A => 0x0314,   0x7B => 0x0314,
            0x7C => 0x0314,   0x7D => 0x0314,   0x7E => 0x0314,   0x7F => 0x0314,
            0x80 => 0x0314,   0x81 => 0x0314,   0x82 => 0x0314,   0x83 => 0x0314,
            0x84 => 0x0314,   0x85 => 0x0314,   0x86 => 0x0314,   0x87 => 0x0314,
            0x88 => 0x0314,   0x89 => 0x0314,   0x8A => 0x0314,   0x8B => 0x0314,
            0x8C => 0x0314,   0x8D => 0x0314,   0x8E => 0x0314,   0x8F => 0x0314,
            0x90 => 0x0314,   0x91 => 0x0314,   0x92 => 0x0314,   0x93 => 0x0314,
            0x94 => 0x0314,   0x95 => 0x0314,   0x96 => 0x0314,   0x97 => 0x0314,
            0x98 => 0x0314,   0x99 => 0x0314,   0x9A => 0x0314,   0x9B => 0x0314,
            0x9C => 0x0314,   0x9D => 0x0314,   0x9E => 0x0314,   0x9F => 0x0314,
            0xA0 => 0x0314,   0xA1 => 0x037E,   0xA2 => 0x0346,   0xA3 => 0x03F8,
            0xA4 => 0x01CA,   0xA5 => 0x02EC,   0xA6 => 0x039C,   0xA7 => 0x02EC,
            0xA8 => 0x0396,   0xA9 => 0x039F,   0xAA => 0x03A0,   0xAB => 0x03A0,
            0xAC => 0x0342,   0xAD => 0x0369,   0xAE => 0x033C,   0xAF => 0x039C,
            0xB0 => 0x039C,   0xB1 => 0x0395,   0xB2 => 0x03A2,   0xB3 => 0x03A3,
            0xB4 => 0x01CF,   0xB5 => 0x0373,   0xB6 => 0x0344,   0xB7 => 0x0344,
            0xB8 => 0x0363,   0xB9 => 0x0363,   0xBA => 0x02B8,   0xBB => 0x02B8,
            0xBC => 0x036A,   0xBD => 0x036A,   0xBE => 0x02F8,   0xBF => 0x03B2,
            0xC0 => 0x0303,   0xC1 => 0x0361,   0xC2 => 0x0303,   0xC3 => 0x0378,
            0xC4 => 0x03C7,   0xC5 => 0x0378,   0xC6 => 0x033F,   0xC7 => 0x0369,
            0xC8 => 0x039F,   0xC9 => 0x03CA,   0xCA => 0x0396];

        /* The cmap table is similarly synthesized.
         */
        $cmapData = [
            0x20 => 0x01, 0x2701 => 0x02, 0x2702 => 0x03, 0x2703 => 0x04,
            0x2704 => 0x05, 0x260E => 0x06, 0x2706 => 0x07, 0x2707 => 0x08,
            0x2708 => 0x09, 0x2709 => 0x0A, 0x261B => 0x0B, 0x261E => 0x0C,
            0x270C => 0x0D, 0x270D => 0x0E, 0x270E => 0x0F, 0x270F => 0x10,
            0x2710 => 0x11, 0x2711 => 0x12, 0x2712 => 0x13, 0x2713 => 0x14,
            0x2714 => 0x15, 0x2715 => 0x16, 0x2716 => 0x17, 0x2717 => 0x18,
            0x2718 => 0x19, 0x2719 => 0x1A, 0x271A => 0x1B, 0x271B => 0x1C,
            0x271C => 0x1D, 0x271D => 0x1E, 0x271E => 0x1F, 0x271F => 0x20,
            0x2720 => 0x21, 0x2721 => 0x22, 0x2722 => 0x23, 0x2723 => 0x24,
            0x2724 => 0x25, 0x2725 => 0x26, 0x2726 => 0x27, 0x2727 => 0x28,
            0x2605 => 0x29, 0x2729 => 0x2A, 0x272A => 0x2B, 0x272B => 0x2C,
            0x272C => 0x2D, 0x272D => 0x2E, 0x272E => 0x2F, 0x272F => 0x30,
            0x2730 => 0x31, 0x2731 => 0x32, 0x2732 => 0x33, 0x2733 => 0x34,
            0x2734 => 0x35, 0x2735 => 0x36, 0x2736 => 0x37, 0x2737 => 0x38,
            0x2738 => 0x39, 0x2739 => 0x3A, 0x273A => 0x3B, 0x273B => 0x3C,
            0x273C => 0x3D, 0x273D => 0x3E, 0x273E => 0x3F, 0x273F => 0x40,
            0x2740 => 0x41, 0x2741 => 0x42, 0x2742 => 0x43, 0x2743 => 0x44,
            0x2744 => 0x45, 0x2745 => 0x46, 0x2746 => 0x47, 0x2747 => 0x48,
            0x2748 => 0x49, 0x2749 => 0x4A, 0x274A => 0x4B, 0x274B => 0x4C,
            0x25CF => 0x4D, 0x274D => 0x4E, 0x25A0 => 0x4F, 0x274F => 0x50,
            0x2750 => 0x51, 0x2751 => 0x52, 0x2752 => 0x53, 0x25B2 => 0x54,
            0x25BC => 0x55, 0x25C6 => 0x56, 0x2756 => 0x57, 0x25D7 => 0x58,
            0x2758 => 0x59, 0x2759 => 0x5A, 0x275A => 0x5B, 0x275B => 0x5C,
            0x275C => 0x5D, 0x275D => 0x5E, 0x275E => 0x5F, 0x2768 => 0x60,
            0x2769 => 0x61, 0x276A => 0x62, 0x276B => 0x63, 0x276C => 0x64,
            0x276D => 0x65, 0x276E => 0x66, 0x276F => 0x67, 0x2770 => 0x68,
            0x2771 => 0x69, 0x2772 => 0x6A, 0x2773 => 0x6B, 0x2774 => 0x6C,
            0x2775 => 0x6D, 0x2761 => 0x6E, 0x2762 => 0x6F, 0x2763 => 0x70,
            0x2764 => 0x71, 0x2765 => 0x72, 0x2766 => 0x73, 0x2767 => 0x74,
            0x2663 => 0x75, 0x2666 => 0x76, 0x2665 => 0x77, 0x2660 => 0x78,
            0x2460 => 0x79, 0x2461 => 0x7A, 0x2462 => 0x7B, 0x2463 => 0x7C,
            0x2464 => 0x7D, 0x2465 => 0x7E, 0x2466 => 0x7F, 0x2467 => 0x80,
            0x2468 => 0x81, 0x2469 => 0x82, 0x2776 => 0x83, 0x2777 => 0x84,
            0x2778 => 0x85, 0x2779 => 0x86, 0x277A => 0x87, 0x277B => 0x88,
            0x277C => 0x89, 0x277D => 0x8A, 0x277E => 0x8B, 0x277F => 0x8C,
            0x2780 => 0x8D, 0x2781 => 0x8E, 0x2782 => 0x8F, 0x2783 => 0x90,
            0x2784 => 0x91, 0x2785 => 0x92, 0x2786 => 0x93, 0x2787 => 0x94,
            0x2788 => 0x95, 0x2789 => 0x96, 0x278A => 0x97, 0x278B => 0x98,
            0x278C => 0x99, 0x278D => 0x9A, 0x278E => 0x9B, 0x278F => 0x9C,
            0x2790 => 0x9D, 0x2791 => 0x9E, 0x2792 => 0x9F, 0x2793 => 0xA0,
            0x2794 => 0xA1, 0x2192 => 0xA2, 0x2194 => 0xA3, 0x2195 => 0xA4,
            0x2798 => 0xA5, 0x2799 => 0xA6, 0x279A => 0xA7, 0x279B => 0xA8,
            0x279C => 0xA9, 0x279D => 0xAA, 0x279E => 0xAB, 0x279F => 0xAC,
            0x27A0 => 0xAD, 0x27A1 => 0xAE, 0x27A2 => 0xAF, 0x27A3 => 0xB0,
            0x27A4 => 0xB1, 0x27A5 => 0xB2, 0x27A6 => 0xB3, 0x27A7 => 0xB4,
            0x27A8 => 0xB5, 0x27A9 => 0xB6, 0x27AA => 0xB7, 0x27AB => 0xB8,
            0x27AC => 0xB9, 0x27AD => 0xBA, 0x27AE => 0xBB, 0x27AF => 0xBC,
            0x27B1 => 0xBD, 0x27B2 => 0xBE, 0x27B3 => 0xBF, 0x27B4 => 0xC0,
            0x27B5 => 0xC1, 0x27B6 => 0xC2, 0x27B7 => 0xC3, 0x27B8 => 0xC4,
            0x27B9 => 0xC5, 0x27BA => 0xC6, 0x27BB => 0xC7, 0x27BC => 0xC8,
            0x27BD => 0xC9, 0x27BE => 0xCA];
        require_once 'Zend/Pdf/Cmap.php';
        $this->_cmap = Zend_Pdf_Cmap::cmapWithTypeData(
            Zend_Pdf_Cmap::TYPE_BYTE_ENCODING_STATIC, $cmapData);

        /* Resource dictionary */

        /* The resource dictionary for the standard fonts is sparse because PDF
         * viewers already have all of the metrics data. We only need to provide
         * the font name and encoding method.
         */
        $this->_resource->BaseFont = new Zend_Pdf_Element_Name('ZapfDingbats');

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
