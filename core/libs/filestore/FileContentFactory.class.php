<?php
/**
 * DESCRIPTION
 *
 * @package Core Plus\Core
 * @since 1.9
 * @author Charlie Powell <charlie@eval.bz>
 * @copyright Copyright (C) 2009-2012  Charlie Powell
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/agpl-3.0.txt.
 */


class FileContentFactory {
	public static function GetFromFile(File_Backend $file) {
		switch ($file->getMimetype()) {
			case 'application/x-gzip':
				// gzip can be a wrapper around a lot of things.  
				// Some of them even have their own content functions.
				if (strtolower($file->getExtension()) == 'tgz') return new File_tgz_contents($file);
				else return new File_gz_contents($file);

			case 'text/plain':
				// Sometimes these are actually other files based on the extension.
				if (strtolower($file->getExtension()) == 'asc') return new File_asc_contents($file);
				else return new File_unknown_contents($file);

			case 'text/xml':
				return new File_xml_contents($file);

			case 'application/pgp-signature':
				return new File_asc_contents($file);

			case 'application/zip':
				return new File_zip_contents($file);

			case 'application/octet-stream':
				// These are fun... basically I'm relying on the extension here.
				if($file->getExtension() == 'zip'){
					return new File_zip_contents($file);
				}
				else{
					error_log('@fixme Unknown extension for application/octet-stream mimetype [' . $file->getExtension() . ']');
					return new File_unknown_contents($file);
				}

			default:
				error_log('@fixme Unknown file mimetype [' . $file->getMimetype() . '] with extension [' . $file->getExtension() . ']');
				return new File_unknown_contents($file);
		}
	}
}
