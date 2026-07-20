extends SceneTree

const VolleyMatchScript := preload("res://scripts/volley_match.gd")

var _room_received := false
var _match_payload: Dictionary = {}


func _initialize() -> void:
	_run.call_deferred()


func _run() -> void:
	var mode := "create"
	for argument in OS.get_cmdline_user_args():
		if argument.begins_with("--probe="):
			mode = argument.trim_prefix("--probe=")
	var bridge := root.get_node_or_null("NetworkBridge")
	if not bridge:
		_fail("NetworkBridge 自动加载失败")
		return
	bridge.room_state_changed.connect(func(_payload: Dictionary): _room_received = true)
	bridge.online_match_started.connect(func(payload: Dictionary): _match_payload = payload)
	if mode == "create":
		bridge.create_room("ws://127.0.0.1:9010", "创建者", 0)
	else:
		bridge.join_room("ws://127.0.0.1:9010", "TEST01", "加入者", 1)
	var deadline := Time.get_ticks_msec() + 20000
	while not _room_received and Time.get_ticks_msec() < deadline:
		await process_frame
	if not _room_received:
		_fail("%s 客户端没有收到房间状态" % mode)
		return
	while _match_payload.is_empty() and Time.get_ticks_msec() < deadline:
		await process_frame
	if _match_payload.is_empty():
		_fail("%s 客户端没有收到开赛消息" % mode)
		return
	var match_scene: VolleyMatch = VolleyMatchScript.new()
	match_scene.configure_network_client(int(_match_payload.side))
	match_scene.setup(_match_payload.left_character, _match_payload.right_character, "联机", {})
	root.add_child(match_scene)
	bridge.attach_online_match(match_scene)
	if mode == "create":
		bridge.send_action("hit")
	deadline = Time.get_ticks_msec() + 5000
	while not match_scene.network_snapshot_ready and Time.get_ticks_msec() < deadline:
		await process_frame
	if not match_scene.network_snapshot_ready:
		_fail("%s 客户端没有收到权威比赛快照" % mode)
		return
	print("NETWORK_PROBE_OK mode=%s room=%s side=%d state=%d" % [mode, bridge.current_room_code, bridge.local_side, match_scene.state])
	await create_timer(1.0).timeout
	bridge.leave_online_room()
	quit(0)


func _fail(message: String) -> void:
	push_error("NETWORK_PROBE_FAILED " + message)
	quit(1)
