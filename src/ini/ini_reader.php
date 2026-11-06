<?php
/**
 * File containing the ezcConfigurationIniReader class
 *
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 * 
 *   http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @package Configuration
 * @version //autogen//
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

/**
 * ezcConfigurationIniReader provides functionality for reading INI files into
 * ezcConfiguration objects.
 *
 * Typical usage is to create the reader object and pass the filepath in the
 * constructor:
 * <code>
 * $reader = new ezcConfigurationIniReader( "settings/site.ini" );
 * $reader->load();
 * </code>
 * That makes the class figure out the location and name values automatically.
 *
 * Or generally use the init() function:
 * <code>
 * $reader = new ezcConfigurationIniReader();
 * $reader->init( "settings", "site" );
 * $reader->load();
 * </code>
 *
 * Accessing the configuration object is done by the getConfig() method or by
 * using the return value of load():
 * <code>
 * $conf1 = $reader->load();
 * $conf2 = $reader->getConfig();
 * // $conf1 and $conf2 points to the same object
 * </code>
 *
 * If caching is employed the getTimestamp() method can be used to find the last
 * modification time of the file.
 * <code>
 * $time = $reader->getTimestamp();
 * if ( $time > $cachedTime )
 * {
 *    $reader->load();
 * }
 * </code>
 *
 * Options can be set with the setOptions() method. The only option that this
 * reader supports is the "useComments" option:
 * <code>
 * $reader->setOptions( array( 'useComments' => true ) );
 * </code>
 *
 * Instead of loading the INI file it can be validated with validate(), this will
 * return an ezcConfigurationValidationResult which can be inspected and
 * presented to the end user.
 * <code>
 * $result = $reader->validate();
 * if ( !$result->isValid )
 * {
 *    foreach ( $result->getResultList() as $resultItem )
 *    {
 *        print $resultItem->file . ":" . $resultItem->line . ":" .
 *            $resultItem->column. ":";
 *        print " " . $resultItem->details . "\n";
 *    }
 * }
 * </code>
 *
 * For more information on file based configurations see {@link
 * ezcConfigurationFileReader}.
 *
 * This class uses exceptions and will throw them when the conditions for the
 * operation fails somehow.
 *
 * @package Configuration
 * @version //autogen//
 * @mainclass
 */
class ezcConfigurationIniReader extends ezcConfigurationFileReader
{
    /**
     * Returns 'ini'. The suffix used in the storage filename.
     *
     * @return string
     */
    protected function getSuffix()
    {
        return 'ini';
    }

    /**
     * Loads a configuration object
     *
     * Loads the current config object from a give location which can later be stored
     * with a ezcConfigurationWriter.
     *
     * @see config()
     * @throws ezcConfigurationNoConfigException if there is no config
     *         object to be read from the location.
     * @throws ezcConfigurationInvalidSuffixException if the current
     *         location values cannot be used for reading.
     * @throws ezcConfigurationReadFailureException if the configuration
     *         could not be read from the given location.
     *
     * @return ezcConfiguration
     */
    public function load()
    {
        $parser = new ezcConfigurationIniParser( ezcConfigurationIniParser::PARSE, $this->path );
        $settings = array();
        $comments = array();

        foreach ( new NoRewindIterator( $parser ) as $element )
        {
            if ( $element instanceof ezcConfigurationIniItem )
            {
                switch ( $element->type )
                {
                    case ezcConfigurationIniItem::GROUP_HEADER:
                        $settings[$element->group] = array();
                        if ( !is_null( $element->comments ) )
                        {
                            $comments[$element->group]['#'] = $element->comments;
                        }
                        break;

                    case ezcConfigurationIniItem::SETTING:
                        $path = array_merge(
                            array( $element->group, $element->setting ),
                            $this->parseDimensions( $element->dimensions )
                        );
                        self::assignPath( $settings, $path, $element->value );
                        if ( !is_null( $element->comments ) )
                        {
                            self::assignPath( $comments, $path, $element->comments );
                        }
                        break;
                }
            }
            if ( $element instanceof ezcConfigurationValidationItem )
            {
                throw new ezcConfigurationParseErrorException( $element->file, $element->line, $element->description );
            }
        }

        $this->config = new ezcConfiguration( $settings, $comments );
        return $this->config;
    }

    /**
     * Splits the dimension string of an INI item into its keys.
     *
     * The string has the form ['key1'][]['key2'] as created by
     * ezcConfigurationIniParser, where each key is escaped with addslashes().
     * An empty pair of brackets is returned as null and means "append".
     *
     * @throws ezcConfigurationParseErrorException if the string is malformed.
     * @param string $dimensions
     * @return array
     */
    private function parseDimensions( $dimensions )
    {
        $keys = array();
        $offset = 0;
        $length = strlen( (string) $dimensions );
        while ( $offset < $length )
        {
            if ( !preg_match( "@\\G\\[(?:'((?:[^'\\\\]|\\\\.)*)')?\\]@s", $dimensions, $match, 0, $offset ) )
            {
                throw new ezcConfigurationParseErrorException( $this->path, 0, "Invalid dimension string '{$dimensions}'" );
            }
            $keys[] = isset( $match[1] ) ? stripslashes( $match[1] ) : null;
            $offset += strlen( $match[0] );
        }
        return $keys;
    }

    /**
     * Assigns $value to the element of $target described by the keys in $path.
     *
     * Missing elements are created; a null key appends a new element, like
     * the empty brackets in $array[] = $value.
     *
     * @param array $target
     * @param array $path
     * @param mixed $value
     */
    private static function assignPath( array &$target, array $path, $value )
    {
        $ref =& $target;
        $last = count( $path ) - 1;
        foreach ( $path as $i => $key )
        {
            if ( $i === $last )
            {
                if ( $key === null )
                {
                    $ref[] = $value;
                }
                else
                {
                    $ref[$key] = $value;
                }
                break;
            }
            if ( $key === null )
            {
                $ref[] = array();
                end( $ref );
                $key = key( $ref );
            }
            $ref =& $ref[$key];
        }
        unset( $ref );
    }

    /**
     * Validates the configuration.
     *
     * Validates the configuration at the given location and returns the
     * validation result.
     *
     * If $strict is set it will not validate the file if it contains any
     * errors or warnings. If false it will allow warnings but not errors.
     *
     * @param bool $strict
     * @return ezcConfigurationValidationResult
     */
    public function validate( $strict = false )
    {
        $parserType = $strict ? ezcConfigurationIniParser::VALIDATE_STRICT : ezcConfigurationIniParser::VALIDATE;
        $parser = new ezcConfigurationIniParser( $parserType, $this->path );

        $validationResult = new ezcConfigurationValidationResult( $this->location, $this->name, $this->path );

        foreach ( new NoRewindIterator( $parser ) as $element )
        {
            if ( $element instanceof ezcConfigurationIniItem )
            {
                throw new Exception( "A validating parser emitted a configuration item, which should never happen" );
            }
            if ( $element instanceof ezcConfigurationValidationItem )
            {
                $validationResult->appendItem( $element );
                if ( $element->type == ezcConfigurationValidationItem::ERROR )
                {
                    $validationResult->isValid = false;
                }
                else if ( $element->type == ezcConfigurationValidationItem::WARNING && $parserType == ezcConfigurationIniItem::VALIDATE_STRICT )
                {
                    $validationResult->isValid = false;
                }
            }
        }
        return $validationResult;
    }
}
?>
