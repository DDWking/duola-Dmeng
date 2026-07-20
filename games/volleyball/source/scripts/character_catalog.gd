class_name CharacterCatalog
extends RefCounted

const CHARACTERS := [
	{
		"id": "yan_ge", "name": "炎哥", "role": "高大强攻", "color": "#ff5b68",
		"height": 1.15, "power": 1.20, "reach": 1.12, "speed": 0.88, "jump": 0.95
	},
	{
		"id": "yan_di", "name": "炎弟", "role": "矮小灵活", "color": "#3fd9e6",
		"height": 0.90, "power": 0.90, "reach": 0.92, "speed": 1.18, "jump": 1.08
	}
]


static func get_character(index: int) -> Dictionary:
	return CHARACTERS[clampi(index, 0, CHARACTERS.size() - 1)].duplicate(true)
