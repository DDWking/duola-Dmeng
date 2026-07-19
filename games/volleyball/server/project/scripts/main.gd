extends Control

const VolleyMatch := preload("res://scripts/volley_match.gd")
const CharacterCatalog := preload("res://scripts/character_catalog.gd")
const LeaderboardClientScript := preload("res://scripts/leaderboard_client.gd")
const FONT_PATH := "res://assets/fonts/ZCOOLQingKeHuangYou-Regular.ttf"
const SAVE_PATH := "user://arcade_volley_save.json"
const SAVE_VERSION := 4
const DEFAULT_DIFFICULTY := "普通"

const COLORS := {
	"ink": Color("1b2741"), "panel": Color("ffffff"), "panel_alt": Color("f4f6fc"), "paper": Color("fbfaf7"),
	"cyan": Color("8293e6"), "yellow": Color("6579d8"), "red": Color("ff6b7d"),
	"green": Color("6fb9ad"), "muted": Color("536079"), "pale": Color("dfe3fb"),
	"wash": Color("edf2fb"), "line": Color("cbd3ec")
}

const CHARACTERS := CharacterCatalog.CHARACTERS

const DEFAULT_BINDINGS := {
	"left": KEY_A, "right": KEY_D, "down": KEY_S, "jump": KEY_W,
	"hit": KEY_J, "spike": KEY_K, "dive": KEY_L, "block": KEY_U
}
const ACTION_LABELS := {
	"left": "向左移动", "right": "向右移动", "down": "快速下落", "jump": "跳跃",
	"hit": "垫球／二传／吊球", "spike": "第三触球重扣", "dive": "飞扑", "block": "拦网"
}

var selected_player := 0
var selected_opponent := 1
var screen_name := "选人"
var content: Control
var player_buttons: Array[Button] = []
var opponent_buttons: Array[Button] = []
var save_data := {"version": SAVE_VERSION, "wins": 0, "losses": 0, "nickname": "", "bindings": DEFAULT_BINDINGS.duplicate()}
var binding_buttons: Dictionary = {}
var waiting_action := ""
var online_status_label: Label
var online_server_input: LineEdit
var online_nickname_input: LineEdit
var online_room_input: LineEdit
var online_match: VolleyMatch
var last_result_online := false
var leaderboard_client
var leaderboard_entries: Array = []
var leaderboard_labels: Array[Label] = []
var leaderboard_status_label: Label
var leaderboard_nickname_input: LineEdit
var result_score_label: Label


func _ready() -> void:
	if NetworkBridge.is_dedicated_server:
		visible = false
		return
	_load_save()
	_apply_theme()
	_initialize_leaderboard()
	_connect_network_signals()
	_show_select()


func _connect_network_signals() -> void:
	NetworkBridge.connection_status_changed.connect(_on_network_status)
	NetworkBridge.room_state_changed.connect(_on_room_state_changed)
	NetworkBridge.online_match_started.connect(_on_online_match_started)
	NetworkBridge.online_match_finished.connect(_on_online_match_finished)
	NetworkBridge.online_match_aborted.connect(_on_online_match_aborted)
	NetworkBridge.opponent_connection_changed.connect(_on_opponent_connection_changed)


func _apply_theme() -> void:
	var game_theme := Theme.new()
	game_theme.default_font = load(FONT_PATH)
	game_theme.default_font_size = 20
	game_theme.set_color("font_color", "Label", COLORS.ink)
	game_theme.set_color("font_color", "Button", COLORS.ink)
	game_theme.set_color("font_hover_color", "Button", COLORS.yellow)
	game_theme.set_color("font_pressed_color", "Button", Color.WHITE)
	game_theme.set_color("font_focus_color", "Button", COLORS.yellow)
	game_theme.set_font_size("font_size", "Button", 20)
	game_theme.set_stylebox("normal", "Button", _style(COLORS.panel, COLORS.line, 1))
	game_theme.set_stylebox("hover", "Button", _style(COLORS.panel_alt, COLORS.cyan, 2))
	game_theme.set_stylebox("pressed", "Button", _style(COLORS.yellow, COLORS.yellow, 2))
	game_theme.set_stylebox("focus", "Button", _style(Color.TRANSPARENT, COLORS.cyan, 2))
	game_theme.set_type_variation("PrimaryButton", "Button")
	game_theme.set_color("font_color", "PrimaryButton", Color.WHITE)
	game_theme.set_color("font_hover_color", "PrimaryButton", Color.WHITE)
	game_theme.set_stylebox("normal", "PrimaryButton", _style(COLORS.yellow, COLORS.yellow, 2))
	game_theme.set_stylebox("hover", "PrimaryButton", _style(COLORS.cyan, COLORS.cyan, 2))
	game_theme.set_stylebox("pressed", "PrimaryButton", _style(COLORS.ink, COLORS.ink, 2))
	game_theme.set_type_variation("GhostButton", "Button")
	game_theme.set_stylebox("normal", "GhostButton", _style(Color(COLORS.panel, 0.7), COLORS.cyan, 1))
	game_theme.set_stylebox("hover", "GhostButton", _style(COLORS.wash, COLORS.cyan, 2))
	game_theme.set_color("font_color", "LineEdit", COLORS.ink)
	game_theme.set_color("font_placeholder_color", "LineEdit", Color(COLORS.muted, 0.7))
	game_theme.set_color("caret_color", "LineEdit", COLORS.yellow)
	game_theme.set_stylebox("normal", "LineEdit", _style(Color(COLORS.panel, 0.82), COLORS.line, 1))
	game_theme.set_stylebox("focus", "LineEdit", _style(COLORS.panel, COLORS.cyan, 2))
	theme = game_theme


func _style(fill: Color, border: Color, width: int = 0, radius: int = 6) -> StyleBoxFlat:
	var box := StyleBoxFlat.new()
	box.bg_color = fill
	box.border_color = border
	box.set_border_width_all(width)
	box.set_corner_radius_all(radius)
	box.content_margin_left = 14
	box.content_margin_right = 14
	box.content_margin_top = 8
	box.content_margin_bottom = 8
	return box


func _setup_button(button: Button, variation: StringName = &"Button") -> void:
	button.theme_type_variation = variation
	button.pivot_offset = button.size * 0.5
	button.mouse_entered.connect(_animate_button.bind(button, Vector2(1.018, 1.018)))
	button.mouse_exited.connect(_animate_button.bind(button, Vector2.ONE))
	button.focus_entered.connect(_animate_button.bind(button, Vector2(1.018, 1.018)))
	button.focus_exited.connect(_animate_button.bind(button, Vector2.ONE))


func _animate_button(button: Button, target_scale: Vector2) -> void:
	if not is_instance_valid(button):
		return
	var previous: Variant = button.get_meta("motion_tween") if button.has_meta("motion_tween") else null
	if previous is Tween:
		previous.kill()
	var motion := create_tween().bind_node(button)
	motion.set_trans(Tween.TRANS_CUBIC).set_ease(Tween.EASE_OUT)
	motion.tween_property(button, "scale", target_scale, 0.18)
	button.set_meta("motion_tween", motion)


func _animate_screen_in() -> void:
	content.modulate.a = 0.0
	content.position.y = 8.0
	var entrance := create_tween().bind_node(content)
	entrance.set_parallel(true)
	entrance.set_trans(Tween.TRANS_CUBIC).set_ease(Tween.EASE_OUT)
	entrance.tween_property(content, "modulate:a", 1.0, 0.32)
	entrance.tween_property(content, "position:y", 0.0, 0.38)


func _initialize_leaderboard() -> void:
	if DisplayServer.get_name() == "headless":
		return
	leaderboard_client = LeaderboardClientScript.new()
	add_child(leaderboard_client)
	leaderboard_client.entries_updated.connect(_on_leaderboard_entries)
	leaderboard_client.session_changed.connect(_on_leaderboard_status)
	leaderboard_client.submission_finished.connect(_on_score_submitted)
	leaderboard_client.request_leaderboard()


func _load_save() -> void:
	if not FileAccess.file_exists(SAVE_PATH):
		return
	var file := FileAccess.open(SAVE_PATH, FileAccess.READ)
	if not file:
		return
	var parsed: Variant = JSON.parse_string(file.get_as_text())
	if parsed is Dictionary:
		save_data.wins = int(parsed.get("wins", 0))
		save_data.losses = int(parsed.get("losses", 0))
		save_data.nickname = String(parsed.get("nickname", ""))
		var stored: Dictionary = parsed.get("bindings", {})
		var stored_version := int(parsed.get("version", 1))
		save_data.bindings = _bindings_from_save(stored, stored_version)
		save_data.version = SAVE_VERSION
		if stored_version < SAVE_VERSION:
			_save()


func _bindings_from_save(stored: Dictionary, stored_version: int) -> Dictionary:
	var loaded := DEFAULT_BINDINGS.duplicate()
	for action in DEFAULT_BINDINGS:
		loaded[action] = int(stored.get(action, DEFAULT_BINDINGS[action]))
	var uses_legacy_defaults := stored_version < 2 and int(stored.get("dive", KEY_L)) == KEY_K and int(stored.get("block", KEY_K)) == KEY_L
	if uses_legacy_defaults:
		loaded.dive = KEY_L
		loaded.block = KEY_K
	if stored_version < 3 and int(loaded.block) == KEY_K:
		loaded.spike = KEY_K
		loaded.block = KEY_U
	return loaded


func _save() -> void:
	var file := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if file:
		file.store_string(JSON.stringify(save_data))


func _clear_screen() -> void:
	if is_instance_valid(content):
		content.queue_free()
	content = Control.new()
	content.set_anchors_and_offsets_preset(Control.PRESET_FULL_RECT)
	add_child(content)


func _show_select() -> void:
	screen_name = "选人"
	_clear_screen()
	queue_redraw()

	var title := _label("瓦力波", Vector2(38, 18), Vector2(420, 48), 40, COLORS.yellow)
	content.add_child(title)
	content.add_child(_label("POCKET VOLLEY / DAILY MATCH", Vector2(40, 61), Vector2(360, 22), 14, COLORS.muted))
	var record := _label("%d 胜  /  %d 负" % [int(save_data.wins), int(save_data.losses)], Vector2(650, 27), Vector2(170, 30), 17, COLORS.ink, HORIZONTAL_ALIGNMENT_RIGHT)
	content.add_child(record)
	var settings_button := Button.new()
	settings_button.text = "设置"
	settings_button.tooltip_text = "按键设置"
	settings_button.position = Vector2(842, 20)
	settings_button.size = Vector2(88, 40)
	settings_button.pressed.connect(_show_key_settings)
	content.add_child(settings_button)
	_setup_button(settings_button, &"GhostButton")
	content.add_child(_label("选择上场角色", Vector2(38, 103), Vector2(264, 28), 21, COLORS.yellow))
	content.add_child(_label("选择对手", Vector2(324, 103), Vector2(264, 28), 21, COLORS.red))
	content.add_child(_label("VS", Vector2(286, 105), Vector2(38, 28), 15, COLORS.muted, HORIZONTAL_ALIGNMENT_CENTER))

	player_buttons.clear()
	opponent_buttons.clear()
	for index in CHARACTERS.size():
		var player_button := _character_button(index, Vector2(38, 140 + index * 88), true)
		content.add_child(player_button)
		player_buttons.append(player_button)
		var opponent_button := _character_button(index, Vector2(324, 140 + index * 88), false)
		content.add_child(opponent_button)
		opponent_buttons.append(opponent_button)

	content.add_child(_label("VISITOR SCOREBOARD", Vector2(654, 103), Vector2(250, 26), 15, COLORS.yellow))
	content.add_child(_label("单人挑战榜", Vector2(654, 124), Vector2(250, 26), 20, COLORS.ink))
	leaderboard_nickname_input = LineEdit.new()
	leaderboard_nickname_input.position = Vector2(654, 156)
	leaderboard_nickname_input.size = Vector2(254, 40)
	leaderboard_nickname_input.max_length = 12
	leaderboard_nickname_input.placeholder_text = "你的排行榜昵称"
	leaderboard_nickname_input.text = String(save_data.nickname)
	content.add_child(leaderboard_nickname_input)
	leaderboard_status_label = _label("每位访客只展示个人最高分", Vector2(654, 195), Vector2(254, 24), 13, COLORS.muted)
	content.add_child(leaderboard_status_label)
	leaderboard_labels.clear()
	for index in 8:
		var row_y := 222 + index * 31
		var name_label := _label("", Vector2(654, row_y), Vector2(176, 27), 15, COLORS.ink)
		var score_label := _label("", Vector2(830, row_y), Vector2(78, 27), 15, COLORS.yellow, HORIZONTAL_ALIGNMENT_RIGHT)
		content.add_child(name_label)
		content.add_child(score_label)
		leaderboard_labels.append(name_label)
		leaderboard_labels.append(score_label)
	_render_leaderboard()

	var start := Button.new()
	start.text = "开始单人挑战"
	start.position = Vector2(38, 421)
	start.size = Vector2(344, 64)
	start.add_theme_font_size_override("font_size", 25)
	start.pressed.connect(_start_match)
	content.add_child(start)
	_setup_button(start, &"PrimaryButton")
	content.add_child(_label("11 分制  /  三局两胜  /  自动平衡电脑", Vector2(38, 372), Vector2(550, 32), 16, COLORS.muted))
	var online := Button.new()
	online.text = "联机 1V1"
	online.position = Vector2(400, 421)
	online.size = Vector2(188, 64)
	online.pressed.connect(_show_online_lobby)
	content.add_child(online)
	_setup_button(online, &"GhostButton")
	_update_selection()
	_animate_screen_in()


func _character_button(index: int, position: Vector2, player_side: bool) -> Button:
	var data: Dictionary = CHARACTERS[index]
	var button := Button.new()
	button.position = position
	button.size = Vector2(264, 76)
	button.alignment = HORIZONTAL_ALIGNMENT_CENTER
	button.text = "%s\n%s" % [String(data.name), String(data.role)]
	button.add_theme_font_size_override("font_size", 21)
	button.tooltip_text = "速度 %.0f%%  弹跳 %.0f%%  力量 %.0f%%  臂展 %.0f%%" % [
		float(data.speed) * 100.0, float(data.jump) * 100.0,
		float(data.power) * 100.0, float(data.reach) * 100.0
	]
	button.pressed.connect((_select_player if player_side else _select_opponent).bind(index))
	_setup_button(button)
	return button


func _select_player(index: int) -> void:
	selected_player = index
	if selected_opponent == selected_player:
		selected_opponent = (selected_opponent + 1) % CHARACTERS.size()
	_update_selection()


func _select_opponent(index: int) -> void:
	selected_opponent = index
	if selected_opponent == selected_player:
		selected_player = (selected_player + 1) % CHARACTERS.size()
	_update_selection()


func _update_selection() -> void:
	for index in CHARACTERS.size():
		_update_character_style(player_buttons[index], index == selected_player, CHARACTERS[index])
		_update_character_style(opponent_buttons[index], index == selected_opponent, CHARACTERS[index])


func _update_character_style(button: Button, selected: bool, data: Dictionary) -> void:
	var accent := Color(String(data.color))
	button.add_theme_stylebox_override("normal", _style(Color(accent, 0.13) if selected else Color(COLORS.panel, 0.78), accent if selected else COLORS.line, 3 if selected else 1))
	button.add_theme_stylebox_override("hover", _style(Color(accent, 0.09), accent, 2))
	button.add_theme_color_override("font_color", COLORS.ink)


func _start_match() -> void:
	_store_leaderboard_nickname()
	if is_instance_valid(leaderboard_client):
		leaderboard_client.start_session()
	screen_name = "比赛"
	_clear_screen()
	queue_redraw()
	var match_scene := VolleyMatch.new()
	match_scene.set_anchors_and_offsets_preset(Control.PRESET_FULL_RECT)
	match_scene.setup(CHARACTERS[selected_player], CHARACTERS[selected_opponent], DEFAULT_DIFFICULTY, save_data.bindings)
	match_scene.finished.connect(_show_result)
	match_scene.quit_requested.connect(_show_select)
	content.add_child(match_scene)


func _show_online_lobby(message: String = "") -> void:
	screen_name = "联机大厅"
	_clear_screen()
	queue_redraw()
	content.add_child(_label("联机对战", Vector2(30, 22), Vector2(420, 54), 38, COLORS.yellow))
	content.add_child(_label("ONLINE 1V1 ROOM", Vector2(32, 68), Vector2(320, 24), 15, COLORS.muted))
	content.add_child(_label("服务器地址", Vector2(74, 116), Vector2(150, 42), 19, COLORS.ink))
	online_server_input = LineEdit.new()
	online_server_input.position = Vector2(224, 116)
	online_server_input.size = Vector2(650, 42)
	online_server_input.text = NetworkBridge.server_url
	online_server_input.placeholder_text = NetworkBridge.default_server_url
	content.add_child(online_server_input)
	content.add_child(_label("游客昵称", Vector2(74, 180), Vector2(150, 42), 19, COLORS.ink))
	online_nickname_input = LineEdit.new()
	online_nickname_input.position = Vector2(224, 180)
	online_nickname_input.size = Vector2(310, 42)
	online_nickname_input.max_length = 12
	online_nickname_input.text = NetworkBridge.nickname if not NetworkBridge.nickname.is_empty() else String(save_data.nickname)
	online_nickname_input.placeholder_text = "输入昵称"
	content.add_child(online_nickname_input)
	var chosen: Dictionary = CHARACTERS[selected_player]
	content.add_child(_label("出战角色  %s / %s" % [String(chosen.name), String(chosen.role)], Vector2(570, 180), Vector2(304, 42), 18, Color(String(chosen.color)), HORIZONTAL_ALIGNMENT_RIGHT))
	var create := Button.new()
	create.text = "创建房间"
	create.position = Vector2(74, 260)
	create.size = Vector2(360, 62)
	create.add_theme_stylebox_override("normal", _style(Color(COLORS.red, 0.2), COLORS.red, 3))
	create.pressed.connect(_create_online_room)
	content.add_child(create)
	_setup_button(create, &"PrimaryButton")
	content.add_child(_label("房间码", Vector2(74, 350), Vector2(130, 48), 19, COLORS.ink))
	online_room_input = LineEdit.new()
	online_room_input.position = Vector2(194, 350)
	online_room_input.size = Vector2(240, 48)
	online_room_input.max_length = 6
	online_room_input.placeholder_text = "六位房间码"
	online_room_input.text = NetworkBridge.current_room_code
	content.add_child(online_room_input)
	var join := Button.new()
	join.text = "加入房间"
	join.position = Vector2(458, 350)
	join.size = Vector2(210, 48)
	join.pressed.connect(_join_online_room)
	content.add_child(join)
	_setup_button(join, &"GhostButton")
	if NetworkBridge.has_saved_session():
		var reconnect := Button.new()
		reconnect.text = "恢复上局"
		reconnect.position = Vector2(690, 350)
		reconnect.size = Vector2(184, 48)
		reconnect.add_theme_stylebox_override("normal", _style(Color(COLORS.green, 0.14), COLORS.green, 2))
		reconnect.pressed.connect(NetworkBridge.reconnect_saved_session)
		content.add_child(reconnect)
		_setup_button(reconnect, &"GhostButton")
	online_status_label = _label(message if not message.is_empty() else "创建房间后，把房间码发给好友", Vector2(74, 418), Vector2(800, 44), 18, COLORS.green, HORIZONTAL_ALIGNMENT_CENTER)
	content.add_child(online_status_label)
	var back := Button.new()
	back.text = "返回选人"
	back.position = Vector2(74, 478)
	back.size = Vector2(220, 46)
	back.pressed.connect(_leave_online_lobby)
	content.add_child(back)
	_setup_button(back)
	_animate_screen_in()


func _create_online_room() -> void:
	_save_online_nickname()
	_set_online_status("正在创建房间…", false)
	NetworkBridge.create_room(online_server_input.text, online_nickname_input.text, selected_player)


func _join_online_room() -> void:
	if online_room_input.text.strip_edges().is_empty():
		_set_online_status("请输入房间码", true)
		return
	_save_online_nickname()
	_set_online_status("正在加入房间…", false)
	NetworkBridge.join_room(online_server_input.text, online_room_input.text, online_nickname_input.text, selected_player)


func _leave_online_lobby() -> void:
	if NetworkBridge.has_saved_session():
		NetworkBridge.leave_online_room()
	_show_select()


func _on_network_status(text: String, is_error: bool) -> void:
	_set_online_status(text, is_error)


func _on_room_state_changed(payload: Dictionary) -> void:
	if screen_name != "联机大厅":
		_show_online_lobby()
	if is_instance_valid(online_room_input):
		online_room_input.text = String(payload.get("code", ""))
	var opponent := String(payload.get("opponent", ""))
	if opponent.is_empty():
		_set_online_status("房间 %s　等待对手加入…" % String(payload.get("code", "")), false)
	else:
		_set_online_status("对手 %s 已加入，正在开赛" % opponent, false)


func _on_online_match_started(payload: Dictionary) -> void:
	if screen_name == "联机比赛" and is_instance_valid(online_match):
		online_match.game_paused = false
		return
	screen_name = "联机比赛"
	_clear_screen()
	queue_redraw()
	online_match = VolleyMatch.new()
	online_match.configure_network_client(int(payload.get("side", -1)))
	online_match.setup(payload.get("left_character", CHARACTERS[0]), payload.get("right_character", CHARACTERS[1]), "联机", save_data.bindings)
	online_match.set_anchors_and_offsets_preset(Control.PRESET_FULL_RECT)
	online_match.quit_requested.connect(_show_select)
	content.add_child(online_match)
	NetworkBridge.attach_online_match(online_match)


func _on_online_match_finished(result: Dictionary) -> void:
	NetworkBridge.attach_online_match(null)
	online_match = null
	_show_result(result, true)


func _on_online_match_aborted(reason: String) -> void:
	NetworkBridge.attach_online_match(null)
	online_match = null
	_show_online_lobby(reason)
	_set_online_status(reason, true)


func _on_opponent_connection_changed(connected: bool, seconds_left: int) -> void:
	if not is_instance_valid(online_match):
		return
	online_match.game_paused = not connected
	if connected:
		online_match.point_message = "对手已重新连接"
		online_match.point_message_time = 1.2
	else:
		online_match.point_message = "对手掉线　等待重连 %d秒" % seconds_left
		online_match.point_message_time = 30.0
	online_match.queue_redraw()


func _set_online_status(text: String, is_error: bool) -> void:
	if is_instance_valid(online_status_label):
		online_status_label.text = text
		online_status_label.add_theme_color_override("font_color", COLORS.red if is_error else COLORS.green)


func _save_online_nickname() -> void:
	if not is_instance_valid(online_nickname_input):
		return
	save_data.nickname = online_nickname_input.text.strip_edges().substr(0, 12)
	_save()


func _store_leaderboard_nickname() -> void:
	if is_instance_valid(leaderboard_nickname_input):
		save_data.nickname = leaderboard_nickname_input.text.strip_edges().substr(0, 12)
	_save()


func _leaderboard_nickname() -> String:
	var nickname := String(save_data.nickname).strip_edges()
	return nickname if not nickname.is_empty() else "匿名球员"


func _on_leaderboard_entries(entries: Array) -> void:
	leaderboard_entries = entries.duplicate(true)
	_render_leaderboard()


func _render_leaderboard() -> void:
	if leaderboard_labels.size() != 16:
		return
	for index in 8:
		var name_label := leaderboard_labels[index * 2]
		var score_label := leaderboard_labels[index * 2 + 1]
		if index < leaderboard_entries.size() and leaderboard_entries[index] is Dictionary:
			var entry: Dictionary = leaderboard_entries[index]
			name_label.text = "%02d  %s" % [index + 1, String(entry.get("nickname", "匿名球员"))]
			score_label.text = "%05d" % int(entry.get("score", 0))
		else:
			name_label.text = "%02d  ---" % [index + 1]
			score_label.text = "-----"


func _on_leaderboard_status(_ready: bool, message: String) -> void:
	if is_instance_valid(leaderboard_status_label):
		leaderboard_status_label.text = message


func _on_score_submitted(response: Dictionary, message: String) -> void:
	if not is_instance_valid(result_score_label):
		return
	if response.has("score"):
		result_score_label.text = "本场积分  %05d  /  已登记" % int(response.score)
		result_score_label.add_theme_color_override("font_color", COLORS.yellow)
	else:
		result_score_label.text = message
		result_score_label.add_theme_color_override("font_color", COLORS.muted)


func _estimated_score(result: Dictionary) -> int:
	var score := 1200 if bool(result.get("victory", false)) else 250
	score += mini(2, int(result.get("player_sets", 0))) * 260
	score += mini(99, int(result.get("spikes", 0))) * 35
	score += mini(99, int(result.get("saves", 0))) * 30
	score += mini(99, int(result.get("blocks", 0))) * 45
	score += mini(150, int(result.get("perfect_touches", 0))) * 30
	score += mini(30, int(result.get("max_combo", 0))) * 80
	return mini(99999, score)


func _show_result(result: Dictionary, from_online: bool = false) -> void:
	last_result_online = from_online
	if bool(result.victory):
		save_data.wins = int(save_data.wins) + 1
	else:
		save_data.losses = int(save_data.losses) + 1
	_save()
	screen_name = "结算"
	_clear_screen()
	queue_redraw()
	var outcome_color: Color = COLORS.yellow if bool(result.victory) else COLORS.red
	content.add_child(_label("MATCH REPORT", Vector2(0, 34), Vector2(960, 25), 15, COLORS.muted, HORIZONTAL_ALIGNMENT_CENTER))
	content.add_child(_label("比赛胜利" if bool(result.victory) else "比赛惜败", Vector2(0, 58), Vector2(960, 62), 45, outcome_color, HORIZONTAL_ALIGNMENT_CENTER))
	content.add_child(_label("%d" % int(result.player_sets), Vector2(320, 132), Vector2(110, 70), 58, COLORS.cyan, HORIZONTAL_ALIGNMENT_CENTER))
	content.add_child(_label(":", Vector2(430, 132), Vector2(100, 70), 48, COLORS.ink, HORIZONTAL_ALIGNMENT_CENTER))
	content.add_child(_label("%d" % int(result.cpu_sets), Vector2(530, 132), Vector2(110, 70), 58, COLORS.red, HORIZONTAL_ALIGNMENT_CENTER))
	content.add_child(_label("局分", Vector2(0, 199), Vector2(960, 26), 16, COLORS.muted, HORIZONTAL_ALIGNMENT_CENTER))
	content.add_child(_label("最后一局  %02d : %02d" % [int(result.player_score), int(result.cpu_score)], Vector2(0, 230), Vector2(960, 34), 23, COLORS.ink, HORIZONTAL_ALIGNMENT_CENTER))
	var stats := [
		["扣球", int(result.get("spikes", 0)), COLORS.red],
		["救球", int(result.get("saves", 0)), COLORS.green],
		["拦网", int(result.get("blocks", 0)), COLORS.cyan],
		["完美触球", int(result.get("perfect_touches", 0)), COLORS.yellow],
		["最高连击", int(result.get("max_combo", 0)), COLORS.ink]
	]
	for index in stats.size():
		var stat_x := 78 + index * 161
		content.add_child(_label(String(stats[index][0]), Vector2(stat_x, 292), Vector2(154, 24), 15, COLORS.muted, HORIZONTAL_ALIGNMENT_CENTER))
		content.add_child(_label("%02d" % int(stats[index][1]), Vector2(stat_x, 316), Vector2(154, 38), 29, Color(stats[index][2]), HORIZONTAL_ALIGNMENT_CENTER))
	result_score_label = _label("", Vector2(0, 378), Vector2(960, 34), 19, COLORS.yellow, HORIZONTAL_ALIGNMENT_CENTER)
	content.add_child(result_score_label)
	if from_online:
		result_score_label.text = "联机对战暂不计入单人挑战榜"
		result_score_label.add_theme_color_override("font_color", COLORS.muted)
	else:
		result_score_label.text = "预计积分  %05d  /  正在登记" % _estimated_score(result)
		if is_instance_valid(leaderboard_client):
			leaderboard_client.submit_score(_leaderboard_nickname(), result)
		else:
			result_score_label.text = "本场积分  %05d" % _estimated_score(result)
	var retry := Button.new()
	retry.text = "再开一局" if from_online else "再战一场"
	retry.position = Vector2(245, 446)
	retry.size = Vector2(220, 56)
	retry.pressed.connect(_show_online_lobby if from_online else _start_match)
	content.add_child(retry)
	_setup_button(retry, &"PrimaryButton")
	var back := Button.new()
	back.text = "返回联机" if from_online else "返回选人"
	back.position = Vector2(495, 446)
	back.size = Vector2(220, 56)
	back.pressed.connect(_show_online_lobby if from_online else _show_select)
	content.add_child(back)
	_setup_button(back, &"GhostButton")
	_animate_screen_in()


func _show_key_settings() -> void:
	screen_name = "按键设置"
	waiting_action = ""
	_clear_screen()
	queue_redraw()
	content.add_child(_label("按键设置", Vector2(30, 22), Vector2(400, 55), 38, COLORS.yellow))
	content.add_child(_label("KEY CONFIGURATION", Vector2(32, 69), Vector2(320, 24), 15, COLORS.muted))
	content.add_child(_label("点击键位后直接按下新键", Vector2(560, 34), Vector2(360, 32), 18, COLORS.muted, HORIZONTAL_ALIGNMENT_RIGHT))
	content.add_child(_label("移动", Vector2(58, 116), Vector2(360, 28), 20, COLORS.cyan))
	content.add_child(_label("比赛动作", Vector2(548, 116), Vector2(360, 28), 20, COLORS.red))
	binding_buttons.clear()
	var groups := [["left", "right", "down", "jump"], ["hit", "spike", "dive", "block"]]
	for column in groups.size():
		for row in groups[column].size():
			var action: String = groups[column][row]
			content.add_child(_label(ACTION_LABELS[action], Vector2(60 + column * 490, 154 + row * 62), Vector2(230, 44), 20, COLORS.ink))
			var button := Button.new()
			button.position = Vector2(295 + column * 490, 154 + row * 62)
			button.size = Vector2(120, 44)
			button.text = _key_name(int(save_data.bindings[action]))
			button.pressed.connect(_wait_for_key.bind(action))
			content.add_child(button)
			_setup_button(button)
			binding_buttons[action] = button
	var reset := Button.new()
	reset.text = "恢复默认"
	reset.position = Vector2(250, 444)
	reset.size = Vector2(210, 54)
	reset.pressed.connect(_reset_bindings)
	content.add_child(reset)
	_setup_button(reset, &"GhostButton")
	var done := Button.new()
	done.text = "保存并返回"
	done.position = Vector2(500, 444)
	done.size = Vector2(210, 54)
	done.pressed.connect(func(): _save(); _show_select())
	content.add_child(done)
	_setup_button(done, &"PrimaryButton")
	_animate_screen_in()


func _wait_for_key(action: String) -> void:
	waiting_action = action
	for name in binding_buttons:
		var button: Button = binding_buttons[name]
		if name == action:
			button.text = "请按新键"
			button.add_theme_stylebox_override("normal", _style(Color(COLORS.cyan, 0.18), COLORS.cyan, 3))
			button.add_theme_color_override("font_color", COLORS.cyan)
		else:
			button.text = _key_name(int(save_data.bindings[name]))
			button.remove_theme_stylebox_override("normal")
			button.remove_theme_color_override("font_color")


func _input(event: InputEvent) -> void:
	if screen_name != "按键设置" or waiting_action.is_empty():
		return
	if event is InputEventKey and event.pressed and not event.echo:
		var new_key: Key = event.physical_keycode
		if new_key == KEY_ESCAPE:
			waiting_action = ""
			_refresh_binding_buttons()
			return
		var old_key := int(save_data.bindings[waiting_action])
		for action in save_data.bindings:
			if action != waiting_action and int(save_data.bindings[action]) == int(new_key):
				save_data.bindings[action] = old_key
		save_data.bindings[waiting_action] = int(new_key)
		waiting_action = ""
		_refresh_binding_buttons()
		get_viewport().set_input_as_handled()


func _reset_bindings() -> void:
	save_data.bindings = DEFAULT_BINDINGS.duplicate()
	waiting_action = ""
	_refresh_binding_buttons()


func _refresh_binding_buttons() -> void:
	for action in binding_buttons:
		var button: Button = binding_buttons[action]
		button.text = _key_name(int(save_data.bindings[action]))
		button.remove_theme_stylebox_override("normal")
		button.remove_theme_color_override("font_color")


func _key_name(keycode: int) -> String:
	var text := OS.get_keycode_string(keycode)
	return text if not text.is_empty() else str(keycode)


func _label(text: String, position: Vector2, label_size: Vector2, font_size: int, color: Color, alignment: HorizontalAlignment = HORIZONTAL_ALIGNMENT_LEFT) -> Label:
	var label := Label.new()
	label.text = text
	label.position = position
	label.size = label_size
	label.add_theme_font_size_override("font_size", font_size)
	label.add_theme_color_override("font_color", color)
	label.horizontal_alignment = alignment
	label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
	return label


func _draw() -> void:
	if screen_name in ["比赛", "联机比赛"]:
		return
	draw_rect(Rect2(Vector2.ZERO, size), COLORS.paper)
	draw_circle(Vector2(size.x * 0.82, 138), 265, Color(COLORS.wash, 0.92))
	draw_circle(Vector2(size.x * 0.13, size.y * 0.86), 190, Color("f7ece8", 0.72))
	for y in range(104, int(size.y), 22):
		for x in range(18, int(size.x), 22):
			if x > 610 or y < 92:
				draw_circle(Vector2(x, y), 0.8, Color(COLORS.cyan, 0.12))
	draw_line(Vector2(30, 88), Vector2(size.x - 30, 88), Color(COLORS.line, 0.85), 1)
	draw_line(Vector2(38, 80), Vector2(170, 80), Color(COLORS.red, 0.7), 3)
	if screen_name == "选人":
		draw_rect(Rect2(26, 94, 578, 316), Color(COLORS.panel, 0.64))
		draw_rect(Rect2(26, 94, 578, 316), Color(COLORS.line, 0.78), false, 1)
		draw_rect(Rect2(632, 94, 298, 392), Color(COLORS.panel, 0.78))
		draw_rect(Rect2(632, 94, 298, 392), Color(COLORS.line, 0.9), false, 1)
		draw_line(Vector2(654, 214), Vector2(908, 214), Color(COLORS.line, 0.8), 1)
	elif screen_name == "按键设置":
		draw_rect(Rect2(40, 104, 390, 318), Color(COLORS.panel, 0.72))
		draw_rect(Rect2(530, 104, 390, 318), Color(COLORS.panel, 0.72))
		draw_rect(Rect2(40, 104, 390, 318), COLORS.line, false, 1)
		draw_rect(Rect2(530, 104, 390, 318), COLORS.line, false, 1)
		draw_line(Vector2(480, 116), Vector2(480, 418), Color(COLORS.cyan, 0.35), 1)
	elif screen_name == "结算":
		draw_rect(Rect2(310, 126, 340, 142), Color(COLORS.panel, 0.82))
		draw_rect(Rect2(310, 126, 340, 142), COLORS.line, false, 1)
		draw_rect(Rect2(64, 282, 832, 82), Color(COLORS.panel, 0.68))
		draw_rect(Rect2(64, 282, 832, 82), COLORS.line, false, 1)
	elif screen_name == "联机大厅":
		draw_rect(Rect2(50, 104, 860, 360), Color(COLORS.panel, 0.76))
		draw_rect(Rect2(50, 104, 860, 360), COLORS.line, false, 1)
		draw_line(Vector2(50, 238), Vector2(910, 238), Color(COLORS.cyan, 0.28), 1)
