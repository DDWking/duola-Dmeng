class_name LeaderboardClient
extends Node

signal entries_updated(entries: Array)
signal session_changed(ready: bool, message: String)
signal submission_finished(response: Dictionary, message: String)

const API_PATH := "/wp-json/duola/v1/volleyball"

var api_root := ""
var run_token := ""
var submit_nonce := ""
var read_request: HTTPRequest
var write_request: HTTPRequest
var write_mode := ""


func _ready() -> void:
	if DisplayServer.get_name() == "headless":
		return
	api_root = _resolve_api_root()
	read_request = HTTPRequest.new()
	write_request = HTTPRequest.new()
	read_request.timeout = 8.0
	write_request.timeout = 8.0
	add_child(read_request)
	add_child(write_request)
	read_request.request_completed.connect(_on_read_completed)
	write_request.request_completed.connect(_on_write_completed)


func request_leaderboard() -> void:
	if api_root.is_empty() or not is_instance_valid(read_request):
		return
	var error := read_request.request(api_root + "/leaderboard?limit=8")
	if error != OK:
		session_changed.emit(false, "排行榜暂时离线")


func start_session() -> void:
	run_token = ""
	submit_nonce = ""
	if api_root.is_empty() or not is_instance_valid(write_request):
		session_changed.emit(false, "本地试玩不登记积分")
		return
	write_mode = "session"
	var error := write_request.request(
		api_root + "/session",
		["Content-Type: application/json"],
		HTTPClient.METHOD_POST,
		"{}"
	)
	if error != OK:
		write_mode = ""
		session_changed.emit(false, "积分服务连接失败")


func submit_score(nickname: String, result: Dictionary) -> void:
	if run_token.is_empty() or submit_nonce.is_empty() or not is_instance_valid(write_request):
		submission_finished.emit({}, "本场积分未登记")
		return
	var payload := {
		"token": run_token,
		"nickname": nickname,
		"website": "",
		"player_sets": int(result.get("player_sets", 0)),
		"cpu_sets": int(result.get("cpu_sets", 0)),
		"player_score": int(result.get("player_score", 0)),
		"cpu_score": int(result.get("cpu_score", 0)),
		"spikes": int(result.get("spikes", 0)),
		"saves": int(result.get("saves", 0)),
		"blocks": int(result.get("blocks", 0)),
		"perfect_touches": int(result.get("perfect_touches", 0)),
		"max_combo": int(result.get("max_combo", 0))
	}
	write_mode = "submit"
	var token := run_token
	run_token = ""
	var error := write_request.request(
		api_root + "/scores",
		["Content-Type: application/json", "X-Duola-Volleyball-Nonce: " + submit_nonce],
		HTTPClient.METHOD_POST,
		JSON.stringify(payload)
	)
	if error != OK:
		run_token = token
		write_mode = ""
		submission_finished.emit({}, "积分提交失败")


func _resolve_api_root() -> String:
	var override := OS.get_environment("VOLLEYBALL_API_BASE").strip_edges().trim_suffix("/")
	if not override.is_empty():
		return override + API_PATH
	if OS.has_feature("web"):
		var origin: Variant = JavaScriptBridge.eval("window.location.origin", true)
		if origin is String and not String(origin).is_empty():
			return String(origin).trim_suffix("/") + API_PATH
	return "http://localhost:8080" + API_PATH


func _on_read_completed(result: int, response_code: int, _headers: PackedStringArray, body: PackedByteArray) -> void:
	var response := _decode_response(body)
	if result == HTTPRequest.RESULT_SUCCESS and response_code >= 200 and response_code < 300:
		entries_updated.emit(response.get("entries", []))
	else:
		session_changed.emit(false, _response_message(response, "排行榜暂时离线"))


func _on_write_completed(result: int, response_code: int, _headers: PackedStringArray, body: PackedByteArray) -> void:
	var mode := write_mode
	write_mode = ""
	var response := _decode_response(body)
	var success := result == HTTPRequest.RESULT_SUCCESS and response_code >= 200 and response_code < 300
	if mode == "session":
		if success:
			run_token = String(response.get("token", ""))
			submit_nonce = String(response.get("nonce", ""))
			session_changed.emit(not run_token.is_empty(), "积分挑战已连接")
		else:
			session_changed.emit(false, _response_message(response, "积分服务连接失败"))
	elif mode == "submit":
		if success:
			entries_updated.emit(response.get("entries", []))
			submission_finished.emit(response, "积分已登记")
		else:
			submission_finished.emit({}, _response_message(response, "积分提交失败"))


func _decode_response(body: PackedByteArray) -> Dictionary:
	var response_text := body.get_string_from_utf8().strip_edges()
	if response_text.is_empty():
		return {}
	var parser := JSON.new()
	if parser.parse(response_text) != OK:
		return {}
	var parsed: Variant = parser.data
	return parsed if parsed is Dictionary else {}


func _response_message(response: Dictionary, fallback: String) -> String:
	var message := String(response.get("message", "")).strip_edges()
	return message if not message.is_empty() else fallback
