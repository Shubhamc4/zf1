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

/** Zend_Pdf_Cmap */
require_once 'Zend/Pdf/Cmap.php';

/**
 * Implements the "byte encoding" character map (type 0).
 *
 * This is the (legacy) Apple standard encoding mechanism and provides coverage
 * for characters in the Mac Roman character set only. Consequently, this cmap
 * type should be used only as a last resort.
 *
 * The mapping from Mac Roman to Unicode can be found at
 * {@link http://www.unicode.org/Public/MAPPINGS/VENDORS/APPLE/ROMAN.TXT}.
 *
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Pdf_Cmap_ByteEncoding extends Zend_Pdf_Cmap
{
    /**** Instance Variables ****/

    /**
     * Glyph index array. Stores the actual glyph numbers. The array keys are
     * the translated Unicode code points.
     *
     * @var array
     */
    protected $_glyphIndexArray = [];

    /**** Public Interface ****/

    /* Concrete Class Implementation */

    /**
     * Returns an array of glyph numbers corresponding to the Unicode characters.
     *
     * If a particular character doesn't exist in this font, the special 'missing
     * character glyph' will be substituted.
     *
     * See also {@link glyphNumberForCharacter()}.
     *
     * @param  array  $characterCodes  Array of Unicode character codes (code points).
     * @return array Array of glyph numbers.
     */
    public function glyphNumbersForCharacters($characterCodes)
    {
        $glyphNumbers = [];
        foreach ($characterCodes as $key => $characterCode) {

            if (! isset($this->_glyphIndexArray[$characterCode])) {
                $glyphNumbers[$key] = Zend_Pdf_Cmap::MISSING_CHARACTER_GLYPH;

                continue;
            }

            $glyphNumbers[$key] = $this->_glyphIndexArray[$characterCode];

        }

        return $glyphNumbers;
    }

    /**
     * Returns the glyph number corresponding to the Unicode character.
     *
     * If a particular character doesn't exist in this font, the special 'missing
     * character glyph' will be substituted.
     *
     * See also {@link glyphNumbersForCharacters()} which is optimized for bulk
     * operations.
     *
     * @param  int  $characterCode  Unicode character code (code point).
     * @return int Glyph number.
     */
    public function glyphNumberForCharacter($characterCode)
    {
        if (! isset($this->_glyphIndexArray[$characterCode])) {
            return Zend_Pdf_Cmap::MISSING_CHARACTER_GLYPH;
        }

        return $this->_glyphIndexArray[$characterCode];
    }

    /**
     * Returns an array containing the Unicode characters that have entries in
     * this character map.
     *
     * @return array Unicode character codes.
     */
    public function getCoveredCharacters()
    {
        return array_keys($this->_glyphIndexArray);
    }

    /**
     * Returns an array containing the glyphs numbers that have entries in this character map.
     * Keys are Unicode character codes (integers)
     *
     * This functionality is partially covered by glyphNumbersForCharacters(getCoveredCharacters())
     * call, but this method do it in more effective way (prepare complete list instead of searching
     * glyph for each character code).
     *
     * @internal
     *
     * @return array Array representing <Unicode character code> => <glyph number> pairs.
     */
    public function getCoveredCharactersGlyphs()
    {
        return $this->_glyphIndexArray;
    }

    /* Object Lifecycle */

    /**
     * Object constructor
     *
     * Parses the raw binary table data. Throws an exception if the table is
     * malformed.
     *
     * @param  string  $cmapData  Raw binary cmap table data.
     *
     * @throws Zend_Pdf_Exception
     */
    public function __construct($cmapData)
    {
        /* Sanity check: This table must be exactly 262 bytes long.
         */
        $actualLength = strlen($cmapData);
        if ($actualLength != 262) {
            require_once 'Zend/Pdf/Exception.php';
            throw new Zend_Pdf_Exception('Insufficient table data',
                Zend_Pdf_Exception::CMAP_TABLE_DATA_TOO_SMALL);
        }

        /* Sanity check: Make sure this is right data for this table type.
         */
        $type = $this->_extractUInt2($cmapData, 0);
        if ($type != Zend_Pdf_Cmap::TYPE_BYTE_ENCODING) {
            require_once 'Zend/Pdf/Exception.php';
            throw new Zend_Pdf_Exception('Wrong cmap table type',
                Zend_Pdf_Exception::CMAP_WRONG_TABLE_TYPE);
        }

        $length = $this->_extractUInt2($cmapData, 2);
        if ($length != $actualLength) {
            require_once 'Zend/Pdf/Exception.php';
            throw new Zend_Pdf_Exception("Table length ({$length}) does not match actual length ({$actualLength})",
                Zend_Pdf_Exception::CMAP_WRONG_TABLE_LENGTH);
        }

        /* Mapping tables should be language-independent. The font may not work
         * as expected if they are not. Unfortunately, many font files in the
         * wild incorrectly record a language ID in this field, so we can't
         * call this a failure.
         */
        $language = $this->_extractUInt2($cmapData, 4);
        if ($language != 0) {
            // Record a warning here somehow?
        }

        /* The mapping between the Mac Roman and Unicode characters is static.
         * For simplicity, just put all 256 glyph indices into one array keyed
         * off the corresponding Unicode character.
         */
        $i = 6;
        $this->_glyphIndexArray[0x00] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x01] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x02] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x03] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x04] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x05] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x06] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x07] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x08] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x09] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0B] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0C] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0D] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0E] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0F] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x10] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x11] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x12] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x13] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x14] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x15] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x16] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x17] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x18] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x19] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x1A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x1B] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x1C] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x1D] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x1E] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x1F] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x20] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x21] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x22] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x23] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x24] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x25] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x26] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x27] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x28] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x29] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2B] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2C] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2D] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2E] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2F] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x30] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x31] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x32] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x33] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x34] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x35] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x36] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x37] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x38] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x39] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x3A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x3B] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x3C] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x3D] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x3E] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x3F] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x40] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x41] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x42] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x43] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x44] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x45] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x46] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x47] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x48] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x49] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x4A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x4B] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x4C] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x4D] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x4E] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x4F] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x50] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x51] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x52] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x53] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x54] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x55] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x56] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x57] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x58] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x59] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x5A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x5B] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x5C] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x5D] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x5E] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x5F] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x60] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x61] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x62] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x63] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x64] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x65] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x66] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x67] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x68] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x69] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x6A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x6B] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x6C] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x6D] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x6E] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x6F] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x70] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x71] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x72] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x73] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x74] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x75] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x76] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x77] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x78] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x79] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x7A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x7B] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x7C] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x7D] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x7E] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x7F] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xC4] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xC5] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xC7] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xC9] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xD1] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xD6] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xDC] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xE1] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xE0] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xE2] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xE4] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xE3] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xE5] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xE7] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xE9] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xE8] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xEA] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xEB] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xED] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xEC] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xEE] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xEF] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xF1] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xF3] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xF2] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xF4] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xF6] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xF5] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xFA] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xF9] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xFB] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xFC] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2020] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xB0] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xA2] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xA3] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xA7] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2022] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xB6] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xDF] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xAE] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xA9] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2122] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xB4] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xA8] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2260] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xC6] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xD8] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x221E] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xB1] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2264] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2265] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xA5] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xB5] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2202] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2211] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x220F] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x03C0] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x222B] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xAA] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xBA] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x03A9] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xE6] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xF8] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xBF] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xA1] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xAC] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x221A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0192] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2248] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2206] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xAB] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xBB] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2026] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xA0] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xC0] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xC3] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xD5] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0152] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0153] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2013] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2014] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x201C] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x201D] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2018] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2019] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xF7] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x25CA] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xFF] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0178] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2044] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x20AC] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2039] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x203A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xFB01] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xFB02] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2021] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xB7] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x201A] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x201E] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x2030] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xC2] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xCA] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xC1] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xCB] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xC8] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xCD] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xCE] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xCF] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xCC] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xD3] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xD4] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xF8FF] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xD2] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xDA] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xDB] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xD9] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x0131] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x02C6] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x02DC] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xAF] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x02D8] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x02D9] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x02DA] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0xB8] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x02DD] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x02DB] = ord($cmapData[$i++]);
        $this->_glyphIndexArray[0x02C7] = ord($cmapData[$i]);
    }
}
