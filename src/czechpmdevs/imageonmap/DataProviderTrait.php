<?php

/**
 * ImageOnMap - Easy to use PocketMine plugin, which allows loading images on maps
 * Copyright (C) 2021 - 2023 CzechPMDevs
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace czechpmdevs\imageonmap;

use czechpmdevs\imageonmap\image\Image;
use czechpmdevs\imageonmap\utils\ImageLoader;
use czechpmdevs\imageonmap\utils\PermissionDeniedException;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use function array_key_exists;
use function basename;
use function crc32;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function hash_file;
use function mkdir;
use function substr;

trait DataProviderTrait {
	/** @var array<int, Image> */
	private array $cachedMaps = [];

	/**
	 * @internal
	 *
	 * @throws PermissionDeniedException When the file could not be accessed
	 */
	public function loadCachedMaps(string $path): void {
		$files = glob($path . "/map_*.dat");
		if(!$files) {
			return;
		}

		$serializer = new BigEndianNbtSerializer();
		foreach($files as $file) {
			$content = file_get_contents($file);
			if(!$content) {
				throw new PermissionDeniedException("Could not access file $file");
			}

			$this->cachedMaps[(int)substr(basename($file, ".dat"), 4)] = Image::load($serializer->read($content)->mustGetCompoundTag());
		}
	}

	/**
	 * @internal
	 *
	 * @throws PermissionDeniedException When the file could not be accessed
	 */
	public function saveCachedMaps(string $path): void {
		$serializer = new BigEndianNbtSerializer();
		foreach($this->cachedMaps as $id => $map) {
			if(!file_put_contents($file = "$path/map_$id.dat", $serializer->write(new TreeRoot($map->save())))) {
				throw new PermissionDeniedException("Could not access file $file");
			}
		}
	}

	/**
	 * @internal
	 */
	public function saveCachedMap(string $path, int $id): void {
		if(!isset($this->cachedMaps[$id])) {
			return;
		}

		$serializer = new BigEndianNbtSerializer();
		$map = $this->cachedMaps[$id];
		if(!file_put_contents($file = "$path/map_$id.dat", $serializer->write(new TreeRoot($map->save())))) {
			throw new PermissionDeniedException("Could not access file $file");
		}
	}

	/**
	 * @internal
	 */
	public function loadCachedMap(string $path, int $id): ?Image {
		if(isset($this->cachedMaps[$id])) {
			return $this->cachedMaps[$id];
		}

		$file = "$path/map_$id.dat";
		if(!file_exists($file)) {
			return null;
		}

		$content = file_get_contents($file);
		if($content === false || $content === '') {
			return null;
		}

		$serializer = new BigEndianNbtSerializer();
		return $this->cachedMaps[$id] = Image::load($serializer->read($content)->mustGetCompoundTag());
	}

	/**
	 * @return int $id Returns id of the image loaded from file.
	 *
	 * @throws PermissionDeniedException When the file could not be accessed
	 */
	public function getImageFromFile(string $file, int $xChunkCount, int $yChunkCount, int $xOffset, int $yOffset): int {
		$id = crc32(hash_file("md5", $file) . "$xChunkCount:$yChunkCount:$xOffset:$yOffset");
		if(!array_key_exists($id, $this->cachedMaps)) {
			$this->cachedMaps[$id] = ImageLoader::loadImage($file, $xChunkCount, $yChunkCount, $xOffset, $yOffset);
			@mkdir($this->getDataFolder() . "data", 0777, true);
			$this->saveCachedMap($this->getDataFolder() . "data", $id);
		}

		return $id;
	}

	/**
	 * @internal
	 */
	public function getCachedMap(int $id): Image {
		return $this->cachedMaps[$id];
	}

	/**
	 * @internal
	 */
	public function getCachedMapOrLoad(int $id): ?Image {
		if(isset($this->cachedMaps[$id])) {
			return $this->cachedMaps[$id];
		}

		return $this->loadCachedMap($this->getDataFolder() . "data", $id);
	}

	/**
	 * Clear all cached maps from memory
	 * This will free up RAM but you'll need to reload images if needed
	 */
	public function clearCachedMaps(): int {
		$count = count($this->cachedMaps);
		$this->cachedMaps = [];
		return $count;
	}

	/**
	 * Get the count of cached maps
	 */
	public function getCachedMapsCount(): int {
		return count($this->cachedMaps);
	}

	/**
	 * Remove a specific cached map by ID
	 */
	public function removeCachedMap(int $id): bool {
		if(isset($this->cachedMaps[$id])) {
			unset($this->cachedMaps[$id]);
			return true;
		}
		return false;
	}
}