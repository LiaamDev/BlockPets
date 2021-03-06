<?php

declare(strict_types = 1);

namespace BlockHorizons\BlockPets\commands;

use BlockHorizons\BlockPets\Loader;
use BlockHorizons\BlockPets\sessions\PlayerSession;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class TogglePetCommand extends SessionDependentCommand {

	public function __construct(Loader $loader) {
		parent::__construct($loader, "togglepet", "Toggle pets on/off", "/togglepet <all/showall/hideall/pet name> [player]", ["togglep"]);
		$this->setPermission("blockpets.command.togglepet");
	}

	public function onCommand(CommandSender $sender, string $commandLabel, array $args): bool {
		$loader = $this->getLoader();
		if(!isset($args[0])) {
			$this->sendWarning($sender, TextFormat::RED . $loader->translate("commands.togglepet.no-pet-specified"));
			return false;
		}

		if(isset($args[1])) {
			$session = $this->getOnlinePlayerSession($args[1], $player);
		} elseif(!($sender instanceof Player)) {
			$this->sendConsoleError($sender);
		} else {
			$session = PlayerSession::get($sender);
			$player = $sender;
		}

		switch($args[0]) {
			case "all":
				$changed = 0;
				foreach($session->getPets() as $pet) {
					$pet->updateVisibility(!$pet->getVisibility());
					++$changed;
				}

				if($sender === $player) {
					$sender->sendMessage(TextFormat::GREEN . $loader->translate("commands.togglepet.success-diff", [$changed]));
				} else {
					$sender->sendMessage(TextFormat::GREEN . $loader->translate("commands.togglepet.success-diff-others", [$player->getName(), $changed]));
				}
				return true;
			case "showall":
				$changed = 0;
				foreach($session->getPets() as $pet) {
					if(!$pet->getVisibility()) {
						$pet->updateVisibility(true);
						++$changed;
					}
				}

				if($sender === $player) {
					$sender->sendMessage(TextFormat::GREEN . $loader->translate("commands.togglepet.success-diff", [$changed]));
				} else {
					$sender->sendMessage(TextFormat::GREEN . $loader->translate("commands.togglepet.success-diff-others", [$player->getName(), $changed]));
				}
				return true;
			case "hideall":
				$changed = 0;
				foreach($session->getPets() as $pet) {
					if($pet->getVisibility()) {
						$pet->updateVisibility(false);
						++$changed;
					}
				}

				if($sender === $player) {
					$sender->sendMessage(TextFormat::GREEN . $loader->translate("commands.togglepet.success-diff", [$changed]));
				} else {
					$sender->sendMessage(TextFormat::GREEN . $loader->translate("commands.togglepet.success-diff-others", [$player->getName(), $changed]));
				}
				return true;
			default:
				$pet = $session->getPetByName($args[0]);
				if($pet === null) {
					$sender->sendMessage($this->getLoader()->translate("commands.errors.player.no-pet"));
					return true;
				}

				$pet->updateVisibility($is_visible = !$pet->getVisibility());

				if($sender === $player) {
					$sender->sendMessage(TextFormat::GREEN . $loader->translate("commands.togglepet.success", [$is_visible ? "on" : "off"]));
				} else {
					$sender->sendMessage(TextFormat::GREEN . $loader->translate("commands.togglepet.success-others", [$player->getName(), $is_visible ? "on" : "off"]));
				}
				return true;
		}

		$loader->getDatabase()->togglePets(
			$player = $player->getName(),
			$type = $args[0] === "all" ? null : $args[0],
			function(array $rows) use($loader, $type, $sender, $player, $session): void {
				if(empty($rows)) {
					$this->sendWarning($sender, TextFormat::RED . $loader->translate("commands.errors.player.no-pet"));
					return;
				}

				if($type === null) {
					$pets = [];

					foreach($rows as [
						"PetName" => $petName,
						"Visible" => $isVisible
					]) {
						$pet = $session->getPetByName($petName);
						if($pet !== null) {
							$pets[$pet->getPetName()] = $isVisible;
							$pet->updateVisibility((bool) $isVisible);
						}
					}

					if(empty($pets)) {
						$this->sendWarning($sender, TextFormat::RED . $loader->translate("commands.errors.player.no-pet"));
						return;
					}

					$visible = array_keys($pets, 1, true);
					if(count($visible) === 0) {
						$sender->sendMessage(TextFormat::GREEN . $loader->translate(
							$sender->getName() === $player ? "commands.togglepet.success" : "commands.togglepet.success-others",
							$sender->getName() === $player ? ["off"] : [$player, "off"]
						));
					} elseif(count($visible) === count($pets)) {
						$sender->sendMessage(TextFormat::GREEN . $loader->translate(
							$sender->getName() === $player ? "commands.togglepet.success" : "commands.togglepet.success-others",
							$sender->getName() === $player ? ["on"] : [$player, "on"]
						));
					} else {
						$sender->sendMessage(TextFormat::GREEN . $loader->translate(
							$sender->getName() === $player ? "commands.togglepet.success-diff" : "commands.togglepet.success-diff-others",
							$sender->getName() === $player ? [implode(", ", $visible)] : [$player, implode(", ", $visible)]
						));
					}
				} else {
					if(empty($rows)) {
						$this->sendWarning($sender, $loader->translate("commands.errors.player.no-pet"));
						return;
					}

					["PetName" => $petName, "Visible" => $isVisible] = array_pop($rows);
					$pet = $session->getPetByName($petName);
					if($pet === null) {
						$this->sendWarning(TextFormat::RED . $sender, $loader->translate("commands.errors.player.no-pet"));
						return;
					}

					$pet->updateVisibility((bool) $isVisible);
					$sender->sendMessage(TextFormat::GREEN . $loader->translate(
						$sender->getName() === $player ? "commands.togglepet.success-specific" : "commands.togglepet.success-specific-others",
						$sender->getName() === $player ? [$petName, $isVisible ? "on" : "off"] : [$player, $petName, $isVisible ? "on" : "off"]
					));
				}
			}
		);
		return true;
	}
}
