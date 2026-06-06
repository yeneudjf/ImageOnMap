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

namespace czechpmdevs\imageonmap\utils;

use czechpmdevs\imageonmap\ImageOnMap;
use pocketmine\color\Color;
use pocketmine\utils\AssumptionFailedError;
use function pack;
use function unpack;

class ColorSerializer {
	/** @var array<int, Color> Cache of Color objects by ARGB value */
	private static array $colorCache = [];

	/**
	 * @return Color[][]
	 */
	public static function readColors(string $bytes): array {
		if(!($data = unpack("L*", $bytes))) {
			throw new AssumptionFailedError("Could not unpack Map image color data");
		}

		$colors = [];
		$i = 0;
		for($x = 0; $x < 128; ++$x) {
			$colors[$x] = [];
			for($y = 0; $y < 128; ++$y) {
				$colorValue = (int)($data[++$i] ?? 0);
				
				// Reuse Color objects from cache to reduce memory
				if(!isset(self::$colorCache[$colorValue])) {
					self::$colorCache[$colorValue] = Color::fromARGB($colorValue);
				}
				$colors[$x][$y] = self::$colorCache[$colorValue];
			}
		}

		return $colors;
	}

	/**
	 * @param Color[][] $colors
	 */
	public static function writeColors(array $colors): string {
		$data = [];
		for($x = 0; $x < 128; ++$x) {
			for($y = 0; $y < 128; ++$y) {
				$data[] = $colors[$x][$y]->toARGB();
			}
		}

		return pack("L*", ...$data);
	}
}