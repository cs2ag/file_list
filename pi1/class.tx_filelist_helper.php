<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Moreno Feltscher  <moreno@luagsh.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Helper class for the 'file_list' extension.
 *
 * @package     TYPO3
 * @subpackage  tx_filelist
 * @author      Moreno Feltscher  <moreno@luagsh.ch>
 * @author      Xavier Perseguers  <typo3@perseguers.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @version     SVN: $Id$
 */
class tx_filelist_helper {

	/**
	 * Sorts an array according to a key and a sort direction.
	 *
	 * @param	array		$arr
	 * @param	string		$sortKey
	 * @param	string		$direction: (either 'asc' or 'desc')
	 * @return	array		The sorted array
	 */
	public static function arraySort(array $arr, $sortKey, $direction = 'asc') {
		if (count($arr) > 0) {
			foreach ($arr as $key => $row) {
				$sortArr[$key] = $row[$sortKey];
			}
			array_multisort($sortArr, strtolower($direction) === 'asc' ? SORT_ASC : SORT_DESC, $arr);
		}

		return $arr;
	}

	/**
	 * Gets a list of all files inside a given directory.
	 *
	 * @param	string		$path: Path to the specified directory
	 * @param	boolean		$recursive: Defines whether files should be searched recursively
	 * @return	array		List of all files inside the directory
	 */
	public static function getListOfFiles($path, $recursive = FALSE, $invalidFileNamePattern = '', $invalidFolderNamePattern = '') {
		$result = array();
		$handle =  opendir($path);
		while ($tempName = readdir($handle)) {
			if (($tempName !== '.') && ($tempName !== '..')) {
				$tempPath = $path . '/' . $tempName;
				if (is_dir($tempPath) && self::isValidName($tempName, $invalidFolderNamePattern) && $recursive) {
					$result = array_merge($result, self::getListOfFiles($tempPath, TRUE, $invalidFileNamePattern, $invalidFolderNamePattern));
				}
				elseif (is_file($tempPath) && self::isValidName($tempName, $invalidFileNamePattern)) {
					$result[] = $tempPath;
				}
			}
		}
		closedir($handle);
		return $result;
	}

	/**
	 * Counts the amount of files inside a given directory.
	 *
	 * @param	string		$path: Path to the specified directory
	 * @param	boolean		$recursive: Defines whether files should be counted recursively
	 * @param	string		$invalidFileNamePattern: Invalid filename pattern
	 * @param	string		$invalidFolderNamePattern: Invalid directory name pattern
	 * @return	integer		Number of files in the directory
	 */
	public static function getNumberOfFiles($path, $recursive = FALSE, $invalidFileNamePattern = '', $invalidFolderNamePattern = '') {
		return count(self::getListOfFiles($path, $recursive, $invalidFileNamePattern, $invalidFolderNamePattern));
	}

	/**
	 * Returns the highest timestamp of all files inside a given directory.
	 *
	 * @param	string		$path: Path to the specified directory
	 * @param	boolean		$recursive: Defines whether files should be searched recursively
	 * @param	string		$invalidFileNamePattern: Invalid filename pattern
	 * @param	string		$invalidFolderNamePattern: Invalid directory name pattern
	 * @return	integer		Highest timestamp of all files in the directory
	 */
	public static function getHighestFileTimestamp($path, $recursive = TRUE, $invalidFileNamePattern = '', $invalidFolderNamePattern = '') {
		$allFiles = self::getListOfFiles($path, $recursive, $invalidFileNamePattern, $invalidFolderNamePattern);
		$highestKnown = 0;
		foreach ($allFiles as $val) {
			$currentValue = filemtime($val);
			if ($currentValue > $highestKnown) {
				$highestKnown = $currentValue;
			}
		}
		return $highestKnown;
	}

	/**
	 * Gets content of a directory.
	 *
	 * @param	string		$path: Path to the specified directory
	 * @param	string		$invalidFileNamePattern: Invalid filename pattern
	 * @param	string		$invalidFolderNamePattern: Invalid directory name pattern
	 * @return	array		list(array $directories, array $files)
	 */
	public static function getDirectoryContent($path, $invalidFileNamePattern = '', $invalidFolderNamePattern = '') {
		$dirs = array();
		$files = array();

			// Open the directory and read out all folders and files
		$dh = opendir($path);
		while ($dir_content = readdir($dh)) {
			if ($dir_content !== '.' && $dir_content !== '..') {
				if (is_dir($path . '/' . $dir_content) && self::isValidName($dir_content, $invalidFolderNamePattern)) {
					$dirs[] = array(
						'type' => 'DIRECTORY',
						'name' => $dir_content,
						'date' => self::getHighestFileTimestamp($path . '/' . $dir_content, TRUE, $invalidFileNamePattern, $invalidFolderNamePattern),
						'size' => self::getNumberOfFiles($path . '/' . $dir_content, FALSE, $invalidFileNamePattern, $invalidFolderNamePattern),
						'path' => $path . $dir_content,
						'fullpath' => PATH_site . $path . $dir_content,
					);
				}
				elseif (is_file($path . '/' . $dir_content) && self::isValidName($dir_content, $invalidFileNamePattern)) {
					$files[] = array(
						'type' => 'FILE',
						'name' => $dir_content,
						'date' => filemtime($path . $dir_content),
						'size' => filesize($path . $dir_content),
						'path' => $path . $dir_content,
						'fullpath' => PATH_site . $path . $dir_content,
					);
				}
			}
		}
			// Close the directory
		closedir($dh);

		return array($dirs, $files);
	}

	/**
	 * Sanitizes a path by making sure a trailing slash is present and
	 * all directories are resolved (no more '../' within string).
	 *
	 * @param	string		$path: either an absolute path or a path relative to website root
	 * @return	string
	 */
	public static function sanitizePath($path) {
		if ($path{0} === '/') {
			$prefix = '';
		} else {
			$prefix = realpath(PATH_site) . '/';
			$path = PATH_site . $path;
		}
			// Make sure there is no more ../ inside
		$path = realpath($path);
			// Make it relative again (if needed)
		$path = substr($path, strlen($prefix));
			// Ensure a trailing slash is present
		$path = rtrim($path, '/') . '/';
		return $path;
	}

	/**
	 * Resolves a site-relative path and or filename.
	 *
	 * @param	string		$path
	 * @return	string
	 */
	public static function resolveSiteRelPath($path) {
		if (strcmp(substr($path, 0, 4), 'EXT:')) {
			return $path;
		}
		$path = substr($path, 4);	// Remove 'EXT:' at the beginning
		$extension = substr($path, 0, strpos($path, '/'));
		$references = explode(':', substr($path, strlen($extension) + 1));
		$pathOrFilename = t3lib_extMgm::siteRelPath($extension) . $references[0];

		if (is_dir(PATH_site . $pathOrFilename)) {
			$pathOrFilename = self::sanitizePath($pathOrFilename);
		}

		return $pathOrFilename;
	}

	/**
	 * Generates a proper URL for file links by encoding special characters and spaces.
	 *
	 * @param	string		$path: Path to the file
	 * @return	string
	 */
	public static function generateProperURL($path) {
		return implode('/', array_map('rawurlencode', explode('/', $path)));
	}

	/**
	 * Returns TRUE if $invalidPattern does not match $name.
	 *
	 * @param	string		$name
	 * @param	string		$pattern
	 * @return	boolean
	 */
	protected static function isValidName($name, $invalidPattern) {
		return empty($invalidPattern) || !preg_match($invalidPattern, $name);
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/file_list/pi1/class.tx_filelist_helper.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/file_list/pi1/class.tx_filelist_helper.php']);
}

?>