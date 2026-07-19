extends Node

signal connection_status_changed(text: String, is_error: bool)
signal room_state_changed(payload: Dictionary)
signal online_match_started(payload: Dictionary)
signal online_match_finished(result: Dictionary)
signal online_match_aborted(reason: String)
signal opponent_connection_changed(connected: bool, seconds_left: int)

const VolleyMatchScript := preload("res://scripts/volley_match.gd")
const CharacterCatalogScript := preload("res://scripts/character_catalog.gd")
const SESSION_PATH := "user://online_session.json"
const DEFAULT_SERVER_URL := "ws://127.0.0.1:9001"
const PUBLIC_SERVER_PATH := "/volleyball-ws"
const RECONNECT_WINDOW_MS := 30000
const SNAPSHOT_INTERVAL := 1.0 / 30.0
const VALID_ACTIONS := ["jump", "hit", "spike", "dive", "block"]

var is_dedicated_server := false
var server_port := 9001
var fixed_room_code := ""
var reconnect_window_ms := RECONNECT_WINDOW_MS
var rooms: Dictionary = {}
var peer_rooms: Dictionary = {}

var default_server_url := DEFAULT_SERVER_URL
var server_url := DEFAULT_SERVER_URL
var session_token := ""
var current_room_code := ""
var local_side := 0
var nickname := ""
var character_index := 0
var active_match: VolleyMatch
var _pending_request: Dictionary = {}
var _manual_disconnect := false
var _reconnect_deadline_ms := 0
var _next_reconnect_ms := 0


func _ready() -> void:
	process_mode = Node.PROCESS_MODE_ALWAYS
	default_server_url = _resolve_default_server_url()
	server_url = default_server_url
	multiplayer.peer_connected.connect(_on_peer_connected)
	multiplayer.peer_disconnected.connect(_on_peer_disconnected)
	multiplayer.connected_to_server.connect(_on_connected_to_server)
	multiplayer.connection_failed.connect(_on_connection_failed)
	multiplayer.server_disconnected.connect(_on_server_disconnected)
	var args := OS.get_cmdline_user_args()
	is_dedicated_server = "--server" in args
	for argument in args:
		if argument.begins_with("--port="):
			server_port = int(argument.trim_prefix("--port="))
		elif argument.begins_with("--fixed-room-code="):
			fixed_room_code = _normalize_room_code(argument.trim_prefix("--fixed-room-code="))
		elif argument.begins_with("--reconnect-window-ms="):
			reconnect_window_ms = maxi(1000, int(argument.trim_prefix("--reconnect-window-ms=")))
	if is_dedicated_server:
		_start_server(server_port)
	else:
		_load_session()


func _process(_delta: float) -> void:
	if is_dedicated_server:
		_process_server_rooms()
	elif _reconnect_deadline_ms > 0 and Time.get_ticks_msec() >= _next_reconnect_ms:
		if Time.get_ticks_msec() >= _reconnect_deadline_ms:
			_reconnect_deadline_ms = 0
			_clear_session()
			online_match_aborted.emit("重连超时，比赛已结束且不计胜负")
		else:
			_next_reconnect_ms = Time.get_ticks_msec() + 2000
			_begin_client_connection({"kind": "reconnect", "token": session_token})


func _start_server(port: int) -> Error:
	var peer := WebSocketMultiplayerPeer.new()
	var error := peer.create_server(port, "0.0.0.0")
	if error != OK:
		push_error("联机服务器启动失败：%s" % error_string(error))
		return error
	multiplayer.multiplayer_peer = peer
	print("VOLLEY_SERVER_READY port=%d" % port)
	return OK


func create_room(url: String, player_name: String, chosen_character: int) -> void:
	nickname = _sanitize_nickname(player_name)
	character_index = clampi(chosen_character, 0, CharacterCatalogScript.CHARACTERS.size() - 1)
	server_url = _normalize_server_url(url)
	_begin_client_connection({"kind": "create"})


func join_room(url: String, room_code: String, player_name: String, chosen_character: int) -> void:
	nickname = _sanitize_nickname(player_name)
	character_index = clampi(chosen_character, 0, CharacterCatalogScript.CHARACTERS.size() - 1)
	server_url = _normalize_server_url(url)
	_begin_client_connection({"kind": "join", "code": _normalize_room_code(room_code)})


func reconnect_saved_session() -> void:
	_load_session()
	if session_token.is_empty():
		connection_status_changed.emit("没有可以恢复的联机房间", true)
		return
	_begin_client_connection({"kind": "reconnect", "token": session_token})


func has_saved_session() -> bool:
	return not session_token.is_empty() and not current_room_code.is_empty()


func attach_online_match(match_scene: VolleyMatch) -> void:
	active_match = match_scene


func send_input_state(sequence: int, move_direction: float, fast_fall: bool) -> void:
	if multiplayer.multiplayer_peer and multiplayer.multiplayer_peer.get_connection_status() == MultiplayerPeer.CONNECTION_CONNECTED:
		server_input_state.rpc_id(1, sequence, clampf(move_direction, -1.0, 1.0), fast_fall)


func send_action(action: String) -> void:
	if action in VALID_ACTIONS and multiplayer.multiplayer_peer and multiplayer.multiplayer_peer.get_connection_status() == MultiplayerPeer.CONNECTION_CONNECTED:
		server_action.rpc_id(1, action)


func leave_online_room() -> void:
	_manual_disconnect = true
	if multiplayer.multiplayer_peer and multiplayer.multiplayer_peer.get_connection_status() == MultiplayerPeer.CONNECTION_CONNECTED:
		server_leave_room.rpc_id(1)
	_clear_session()
	active_match = null
	connection_status_changed.emit("已退出联机房间", false)


func _begin_client_connection(request: Dictionary) -> void:
	if is_dedicated_server:
		return
	_manual_disconnect = false
	_pending_request = request.duplicate(true)
	if multiplayer.multiplayer_peer:
		multiplayer.multiplayer_peer = null
	var peer := WebSocketMultiplayerPeer.new()
	var error := peer.create_client(server_url)
	if error != OK:
		connection_status_changed.emit("无法连接服务器：%s" % error_string(error), true)
		return
	multiplayer.multiplayer_peer = peer
	connection_status_changed.emit("正在连接服务器…", false)


func _on_connected_to_server() -> void:
	connection_status_changed.emit("服务器已连接", false)
	var kind := String(_pending_request.get("kind", ""))
	match kind:
		"create":
			server_create_room.rpc_id(1, nickname, character_index)
		"join":
			server_join_room.rpc_id(1, String(_pending_request.get("code", "")), nickname, character_index)
		"reconnect":
			server_reconnect.rpc_id(1, String(_pending_request.get("token", session_token)))


func _on_connection_failed() -> void:
	connection_status_changed.emit("连接服务器失败，请检查地址和服务状态", true)


func _on_server_disconnected() -> void:
	if is_dedicated_server or _manual_disconnect:
		return
	connection_status_changed.emit("连接中断，正在尝试恢复房间…", true)
	if not session_token.is_empty():
		_reconnect_deadline_ms = Time.get_ticks_msec() + RECONNECT_WINDOW_MS
		_next_reconnect_ms = Time.get_ticks_msec() + 1000
		opponent_connection_changed.emit(false, 30)


func _on_peer_connected(_peer_id: int) -> void:
	pass


func _on_peer_disconnected(peer_id: int) -> void:
	if not is_dedicated_server or not peer_rooms.has(peer_id):
		return
	var code := String(peer_rooms[peer_id])
	peer_rooms.erase(peer_id)
	if not rooms.has(code):
		return
	var room: Dictionary = rooms[code]
	var side := _find_side_by_peer(room, peer_id)
	if side == 0:
		return
	var player_info: Dictionary = room.players[side]
	player_info.peer_id = 0
	player_info.disconnected_at = Time.get_ticks_msec()
	room.players[side] = player_info
	if is_instance_valid(room.get("match")):
		room.match.game_paused = true
	rooms[code] = room
	_notify_opponent_connection.call_deferred(room, side, false, ceili(float(reconnect_window_ms) / 1000.0))
	print("ROOM_DISCONNECTED code=%s side=%d" % [code, side])


@rpc("any_peer", "call_remote", "reliable")
func server_create_room(player_name: String, chosen_character: int) -> void:
	if not is_dedicated_server:
		return
	var peer_id := multiplayer.get_remote_sender_id()
	_remove_peer_from_room(peer_id, false)
	var code := _generate_room_code()
	var token := _generate_token()
	var room := {
		"code": code,
		"started": false,
		"match": null,
		"snapshot_accumulator": 0.0,
		"players": {
			-1: _new_player_info(peer_id, player_name, chosen_character, token),
			1: _new_player_info(0, "", 0, "")
		}
	}
	rooms[code] = room
	peer_rooms[peer_id] = code
	_send_room_state(room, -1)
	print("ROOM_CREATED code=%s" % code)


@rpc("any_peer", "call_remote", "reliable")
func server_join_room(room_code: String, player_name: String, chosen_character: int) -> void:
	if not is_dedicated_server:
		return
	var peer_id := multiplayer.get_remote_sender_id()
	var code := _normalize_room_code(room_code)
	if not rooms.has(code):
		client_request_failed.rpc_id(peer_id, "房间不存在或已结束")
		return
	var room: Dictionary = rooms[code]
	var left: Dictionary = room.players[-1]
	if int(left.peer_id) == 0:
		client_request_failed.rpc_id(peer_id, "房主正在重连，请稍后再试")
		return
	var right: Dictionary = room.players[1]
	if int(right.peer_id) != 0 or not String(right.token).is_empty():
		client_request_failed.rpc_id(peer_id, "房间人数已满")
		return
	_remove_peer_from_room(peer_id, false)
	var token := _generate_token()
	room.players[1] = _new_player_info(peer_id, player_name, chosen_character, token)
	rooms[code] = room
	peer_rooms[peer_id] = code
	_start_room_match(code)


@rpc("any_peer", "call_remote", "reliable")
func server_reconnect(token: String) -> void:
	if not is_dedicated_server:
		return
	var peer_id := multiplayer.get_remote_sender_id()
	for code in rooms:
		var room: Dictionary = rooms[code]
		for side in [-1, 1]:
			var info: Dictionary = room.players[side]
			if String(info.token) != token:
				continue
			var disconnected_at := int(info.disconnected_at)
			if int(info.peer_id) != 0 or disconnected_at <= 0 or Time.get_ticks_msec() - disconnected_at > reconnect_window_ms:
				client_request_failed.rpc_id(peer_id, "原房间已无法恢复")
				return
			info.peer_id = peer_id
			info.disconnected_at = 0
			room.players[side] = info
			if is_instance_valid(room.get("match")):
				room.match.game_paused = false
			rooms[code] = room
			peer_rooms[peer_id] = code
			_send_room_state(room, side)
			_send_match_started(room, side)
			_notify_opponent_connection(room, side, true, 0)
			print("ROOM_RECONNECTED code=%s side=%d" % [code, side])
			return
	client_request_failed.rpc_id(peer_id, "没有找到可恢复的房间")


@rpc("any_peer", "call_remote", "unreliable", 0)
func server_input_state(sequence: int, move_direction: float, fast_fall: bool) -> void:
	if not is_dedicated_server:
		return
	var peer_id := multiplayer.get_remote_sender_id()
	var context := _get_player_context(peer_id)
	if context.is_empty():
		return
	var info: Dictionary = context.info
	if sequence <= int(info.last_sequence):
		return
	info.last_sequence = sequence
	context.room.players[context.side] = info
	rooms[context.code] = context.room
	if is_instance_valid(context.room.get("match")):
		context.room.match.set_network_input(int(context.side), clampf(move_direction, -1.0, 1.0), fast_fall)


@rpc("any_peer", "call_remote", "reliable")
func server_action(action: String) -> void:
	if not is_dedicated_server or action not in VALID_ACTIONS:
		return
	var peer_id := multiplayer.get_remote_sender_id()
	var context := _get_player_context(peer_id)
	if context.is_empty() or not is_instance_valid(context.room.get("match")):
		return
	var now := Time.get_ticks_msec()
	var info: Dictionary = context.info
	if now - int(info.last_action_at) < 35:
		return
	info.last_action_at = now
	context.room.players[context.side] = info
	rooms[context.code] = context.room
	context.room.match.trigger_network_action(int(context.side), action)


@rpc("any_peer", "call_remote", "reliable")
func server_leave_room() -> void:
	if is_dedicated_server:
		_remove_peer_from_room(multiplayer.get_remote_sender_id(), true)


@rpc("authority", "call_remote", "reliable")
func client_room_state(payload: Dictionary) -> void:
	server_url = String(payload.get("server_url", server_url))
	current_room_code = String(payload.get("code", ""))
	local_side = int(payload.get("side", 0))
	session_token = String(payload.get("token", ""))
	_reconnect_deadline_ms = 0
	_save_session()
	room_state_changed.emit(payload)


@rpc("authority", "call_remote", "reliable")
func client_match_started(payload: Dictionary) -> void:
	local_side = int(payload.get("side", local_side))
	online_match_started.emit(payload)


@rpc("authority", "call_remote", "unreliable", 0)
func client_snapshot(snapshot: Dictionary) -> void:
	if is_instance_valid(active_match):
		active_match.apply_network_snapshot(snapshot)


@rpc("authority", "call_remote", "reliable")
func client_match_finished(result: Dictionary) -> void:
	_clear_session()
	online_match_finished.emit(result)


@rpc("authority", "call_remote", "reliable")
func client_match_aborted(reason: String) -> void:
	_clear_session()
	if _manual_disconnect:
		_manual_disconnect = false
		return
	online_match_aborted.emit(reason)


@rpc("authority", "call_remote", "reliable")
func client_opponent_connection(connected: bool, seconds_left: int) -> void:
	opponent_connection_changed.emit(connected, seconds_left)


@rpc("authority", "call_remote", "reliable")
func client_request_failed(reason: String) -> void:
	connection_status_changed.emit(reason, true)
	if String(_pending_request.get("kind", "")) == "reconnect":
		_clear_session()


func _process_server_rooms() -> void:
	var now := Time.get_ticks_msec()
	var expired: Array[String] = []
	for code in rooms:
		var room: Dictionary = rooms[code]
		var timed_out := false
		for side in [-1, 1]:
			var info: Dictionary = room.players[side]
			if int(info.disconnected_at) > 0 and now - int(info.disconnected_at) >= reconnect_window_ms:
				timed_out = true
		if timed_out:
			expired.append(String(code))
			continue
		if bool(room.started) and is_instance_valid(room.get("match")) and not room.match.game_paused:
			room.snapshot_accumulator = float(room.snapshot_accumulator) + get_process_delta_time()
			if float(room.snapshot_accumulator) >= SNAPSHOT_INTERVAL:
				room.snapshot_accumulator = fmod(float(room.snapshot_accumulator), SNAPSHOT_INTERVAL)
				_broadcast_snapshot(room)
			rooms[code] = room
	for code in expired:
		_abort_room(code, "对手重连超时，比赛已结束且不计胜负")


func _start_room_match(code: String) -> void:
	var room: Dictionary = rooms[code]
	var left_info: Dictionary = room.players[-1]
	var right_info: Dictionary = room.players[1]
	var left_data := CharacterCatalogScript.get_character(int(left_info.character_index))
	var right_data := CharacterCatalogScript.get_character(int(right_info.character_index))
	left_data.display_name = _display_name(left_info.nickname, left_data.name)
	right_data.display_name = _display_name(right_info.nickname, right_data.name)
	var match_scene: VolleyMatch = VolleyMatchScript.new()
	match_scene.configure_network_server()
	match_scene.setup(left_data, right_data, "联机", {})
	match_scene.visible = false
	add_child(match_scene)
	match_scene.finished.connect(_on_server_match_finished.bind(code))
	room.match = match_scene
	room.started = true
	room.snapshot_accumulator = 0.0
	rooms[code] = room
	_send_room_state(room, -1)
	_send_room_state(room, 1)
	_send_match_started(room, -1)
	_send_match_started(room, 1)
	print("MATCH_STARTED code=%s" % code)


func _send_match_started(room: Dictionary, side: int) -> void:
	var info: Dictionary = room.players[side]
	if not _is_peer_connected(int(info.peer_id)) or not bool(room.started):
		return
	var left: Dictionary = room.players[-1]
	var right: Dictionary = room.players[1]
	var left_data := CharacterCatalogScript.get_character(int(left.character_index))
	var right_data := CharacterCatalogScript.get_character(int(right.character_index))
	left_data.display_name = _display_name(left.nickname, left_data.name)
	right_data.display_name = _display_name(right.nickname, right_data.name)
	client_match_started.rpc_id(int(info.peer_id), {
		"code": room.code,
		"side": side,
		"left_character": left_data,
		"right_character": right_data
	})


func _broadcast_snapshot(room: Dictionary) -> void:
	var snapshot: Dictionary = room.match.make_network_snapshot()
	for side in [-1, 1]:
		var peer_id := int(room.players[side].peer_id)
		if _is_peer_connected(peer_id):
			client_snapshot.rpc_id(peer_id, snapshot)


func _on_server_match_finished(result: Dictionary, code: String) -> void:
	if not rooms.has(code):
		return
	var room: Dictionary = rooms[code]
	for side in [-1, 1]:
		var peer_id := int(room.players[side].peer_id)
		if not _is_peer_connected(peer_id):
			continue
		var side_result := result.duplicate(true)
		if side > 0:
			side_result.victory = not bool(result.victory)
			side_result.player_sets = int(result.cpu_sets)
			side_result.cpu_sets = int(result.player_sets)
			side_result.player_score = int(result.cpu_score)
			side_result.cpu_score = int(result.player_score)
		client_match_finished.rpc_id(peer_id, side_result)
	_destroy_room(code)


func _send_room_state(room: Dictionary, side: int) -> void:
	var info: Dictionary = room.players[side]
	var peer_id := int(info.peer_id)
	if not _is_peer_connected(peer_id):
		return
	var opponent: Dictionary = room.players[-side]
	client_room_state.rpc_id(peer_id, {
		"code": room.code,
		"side": side,
		"token": info.token,
		"nickname": info.nickname,
		"opponent": opponent.nickname,
		"started": room.started
	})


func _notify_opponent_connection(room: Dictionary, changed_side: int, connected: bool, seconds_left: int) -> void:
	var opponent: Dictionary = room.players[-changed_side]
	if _is_peer_connected(int(opponent.peer_id)):
		client_opponent_connection.rpc_id(int(opponent.peer_id), connected, seconds_left)


func _abort_room(code: String, reason: String) -> void:
	if not rooms.has(code):
		return
	var room: Dictionary = rooms[code]
	for side in [-1, 1]:
		var peer_id := int(room.players[side].peer_id)
		if _is_peer_connected(peer_id):
			client_match_aborted.rpc_id(peer_id, reason)
	_destroy_room(code)


func _destroy_room(code: String) -> void:
	if not rooms.has(code):
		return
	var room: Dictionary = rooms[code]
	for side in [-1, 1]:
		var peer_id := int(room.players[side].peer_id)
		if peer_id != 0:
			peer_rooms.erase(peer_id)
	if is_instance_valid(room.get("match")):
		room.match.queue_free()
	rooms.erase(code)


func _remove_peer_from_room(peer_id: int, notify: bool) -> void:
	if not peer_rooms.has(peer_id):
		return
	var code := String(peer_rooms[peer_id])
	if notify:
		_abort_room(code, "对手已退出，比赛结束且不计胜负")
	else:
		_destroy_room(code)


func _get_player_context(peer_id: int) -> Dictionary:
	if not peer_rooms.has(peer_id):
		return {}
	var code := String(peer_rooms[peer_id])
	if not rooms.has(code):
		return {}
	var room: Dictionary = rooms[code]
	var side := _find_side_by_peer(room, peer_id)
	if side == 0:
		return {}
	return {"code": code, "room": room, "side": side, "info": room.players[side]}


func _find_side_by_peer(room: Dictionary, peer_id: int) -> int:
	for side in [-1, 1]:
		if int(room.players[side].peer_id) == peer_id:
			return side
	return 0


func _new_player_info(peer_id: int, player_name: String, chosen_character: int, token: String) -> Dictionary:
	return {
		"peer_id": peer_id,
		"nickname": "" if peer_id == 0 else _sanitize_nickname(player_name),
		"character_index": clampi(chosen_character, 0, CharacterCatalogScript.CHARACTERS.size() - 1),
		"token": token,
		"disconnected_at": 0,
		"last_sequence": -1,
		"last_action_at": 0
	}


func _is_peer_connected(peer_id: int) -> bool:
	if peer_id == 0 or peer_id not in multiplayer.get_peers():
		return false
	var websocket_peer := multiplayer.multiplayer_peer as WebSocketMultiplayerPeer
	if not websocket_peer:
		return true
	var socket := websocket_peer.get_peer(peer_id)
	return socket != null and socket.get_ready_state() == WebSocketPeer.STATE_OPEN


func _generate_room_code() -> String:
	if not fixed_room_code.is_empty() and not rooms.has(fixed_room_code):
		return fixed_room_code
	const ALPHABET := "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"
	for attempt in 100:
		var code := ""
		for index in 6:
			code += ALPHABET[randi_range(0, ALPHABET.length() - 1)]
		if not rooms.has(code):
			return code
	return "%06d" % (randi() % 1000000)


func _generate_token() -> String:
	return "%08x%08x%08x" % [randi(), randi(), Time.get_ticks_msec()]


func _sanitize_nickname(value: String) -> String:
	var result := value.replace("\n", "").replace("\r", "").strip_edges()
	if result.is_empty():
		result = "游客%04d" % randi_range(0, 9999)
	return result.left(12)


func _display_name(player_name: String, character_name: String) -> String:
	return "%s · %s" % [_sanitize_nickname(player_name), character_name]


func _normalize_room_code(value: String) -> String:
	return value.strip_edges().to_upper().replace(" ", "").left(6)


func _normalize_server_url(value: String) -> String:
	var result := value.strip_edges()
	return default_server_url if result.is_empty() else result


func _resolve_default_server_url() -> String:
	if not OS.has_feature("web") or not Engine.has_singleton("JavaScriptBridge"):
		return DEFAULT_SERVER_URL
	var java_script_bridge: Object = Engine.get_singleton("JavaScriptBridge")
	var browser_url: Variant = java_script_bridge.call("eval",
		"(function () { var local = window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost'; if (local) return 'ws://' + window.location.hostname + ':9001'; return (window.location.protocol === 'https:' ? 'wss://' : 'ws://') + window.location.host + '%s'; })()" % PUBLIC_SERVER_PATH,
		true
	)
	if browser_url is String and not browser_url.is_empty():
		return browser_url
	return DEFAULT_SERVER_URL


func _save_session() -> void:
	if is_dedicated_server or session_token.is_empty():
		return
	var file := FileAccess.open(SESSION_PATH, FileAccess.WRITE)
	if file:
		file.store_string(JSON.stringify({
			"url": server_url,
			"token": session_token,
			"code": current_room_code,
			"nickname": nickname,
			"character_index": character_index
		}))


func _load_session() -> void:
	if not FileAccess.file_exists(SESSION_PATH):
		return
	var file := FileAccess.open(SESSION_PATH, FileAccess.READ)
	if not file:
		return
	var parsed: Variant = JSON.parse_string(file.get_as_text())
	if parsed is Dictionary:
		server_url = String(parsed.get("url", default_server_url))
		session_token = String(parsed.get("token", ""))
		current_room_code = String(parsed.get("code", ""))
		nickname = String(parsed.get("nickname", ""))
		character_index = int(parsed.get("character_index", 0))


func _clear_session() -> void:
	session_token = ""
	current_room_code = ""
	local_side = 0
	_reconnect_deadline_ms = 0
	if FileAccess.file_exists(SESSION_PATH):
		DirAccess.remove_absolute(SESSION_PATH)
