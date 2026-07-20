class_name VolleyMatch
extends Control

signal finished(result: Dictionary)
signal quit_requested

enum MatchState { SERVE, PLAY, POINT, SET_BREAK, MATCH_OVER }
enum KAttack { NONE, GROUND_UP, GROUND_SIDE, GROUND_DOWN, AIR_SPIKE }
enum TouchPhase { RECEIVE, SET, ATTACK }
enum ShotRoute { STRAIGHT, CROSS, TIP }
enum RuntimeMode { OFFLINE, NETWORK_SERVER, NETWORK_CLIENT }

const FLOOR_Y := 402.0
const NET_X := 480.0
const NET_TOP := 210.0
const BALL_RADIUS := 14.0
const GRAVITY := 980.0
const BALL_CEILING_Y := -120.0
const LEFT_LIMIT := 45.0
const RIGHT_LIMIT := 915.0
const K_ATTACK_WINDOW := 0.28
const NORMAL_HIT_WINDOW := 0.18
const BACKCOURT_SPIKE_DISTANCE := 150.0
const BACKCOURT_BLOCK_CHANCE := 0.15
const BLOCK_COOLDOWN := 0.85
const PERFECT_TIMING_QUALITY := 0.62
const NETWORK_INPUT_INTERVAL := 1.0 / 30.0
const NETWORK_CORRECTION_RATE := 12.0
const NETWORK_SNAP_DISTANCE := 150.0
const CPU_HUD_RIGHT := 875.0
const PAUSE_BUTTON_RECT := Rect2(894, 16, 46, 42)
const POSE_POINTS := [
	"head", "left_shoulder", "right_shoulder", "left_elbow", "right_elbow",
	"left_hand", "right_hand", "left_hip", "right_hip", "left_knee", "right_knee",
	"left_foot", "right_foot"
]

const COLORS := {
	"ink": Color("1b2741"), "paper": Color("fbfaf7"), "panel": Color("ffffff"),
	"court": Color("e8edf9"), "court_dark": Color("cfd8ee"), "cyan": Color("8293e6"),
	"yellow": Color("6579d8"), "pale": Color("dfe3fb"), "wash": Color("edf2fb"),
	"red": Color("ff6b7d"), "green": Color("6fb9ad"), "muted": Color("536079"),
	"line": Color("cbd3ec")
}

class Actor:
	var data: Dictionary
	var side: int
	var position: Vector2
	var velocity := Vector2.ZERO
	var move_direction := 0.0
	var grounded := true
	var hit_cooldown := 0.0
	var action_lock_time := 0.0
	var dive_time := 0.0
	var dive_direction := 0.0
	var dive_has_hit := false
	var block_time := 0.0
	var block_cooldown := 0.0
	var block_fatigue := 0
	var block_reach_scale := 1.0
	var block_connected := false
	var bump_time := 0.0
	var normal_hit_window := 0.0
	var normal_hit_mode := ""
	var flash_time := 0.0
	var animation_clock := 0.0
	var jump_time := 0.0
	var land_time := 0.0
	var set_time := 0.0
	var spike_time := 0.0
	var spike_ready_time := 0.0
	var spike_lunge_time := 0.0
	var spike_charge := 0.0
	var spike_attempted := false
	var spike_move_direction := 0.0
	var k_attack := KAttack.NONE
	var k_attack_direction := 0.0
	var shot_route := ShotRoute.STRAIGHT
	var fast_fall_held := false
	var serve_time := 0.0
	var hurt_time := 0.0
	var celebrate_time := 0.0
	var turn_time := 0.0
	var last_move_sign := 0.0
	var dust_cooldown := 0.0

	func _init(character: Dictionary, actor_side: int) -> void:
		data = character.duplicate(true)
		side = actor_side
		position = Vector2(205 if side < 0 else 755, FLOOR_Y)

	func color() -> Color:
		return Color(String(data.color))

	func hand_position() -> Vector2:
		var facing := 1.0 if side < 0 else -1.0
		return position + Vector2(
			facing * 28.0 * float(data.reach),
			(-72.0 if not grounded else -54.0) * float(data.height)
		)


var player_data: Dictionary
var cpu_data: Dictionary
var difficulty := "普通"
var bindings: Dictionary = {}
var player: Actor
var cpu: Actor

var state := MatchState.SERVE
var state_timer := 0.0
var serve_side := -1
var last_point_winner := -1
var set_complete := false
var match_complete := false
var player_score := 0
var cpu_score := 0
var player_sets := 0
var cpu_sets := 0

var ball_position := Vector2.ZERO
var ball_velocity := Vector2.ZERO
var previous_ball_position := Vector2.ZERO
var ball_rotation := 0.0
var last_touch_side := 0
var side_touches := 0
var rally_hits := 0
var serve_touch_locked_side := 0

var point_message := ""
var point_message_time := 0.0
var action_message := ""
var action_message_color := Color("ffd447")
var action_message_time := 0.0
var screen_shake := 0.0
var score_pulse_time := 0.0
var timing_flash_time := 0.0
var action_pop_scale := 1.0
var action_tween: Tween
var particles: Array[Dictionary] = []

var ai_think := 0.0
var ai_target_x := 755.0
var ai_error := 0.0
var ai_block_decision_hit := -1
var ai_block_decision_allowed := false
var last_player_attack_from_backcourt := false

var spikes := 0
var saves := 0
var blocks := 0
var performance_score := 0
var perfect_touches := 0
var current_combo := 0
var max_combo := 0
var net_clash_cooldown := 0.0
var pause_button: Button
var pause_layer: Control
var game_paused := false
var runtime_mode := RuntimeMode.OFFLINE
var local_network_side := -1
var network_inputs := {
	-1: {"move": 0.0, "down": false},
	1: {"move": 0.0, "down": false}
}
var network_input_sequence := 0
var network_send_accumulator := 0.0
var network_snapshot_ready := false
var network_ball_target_position := Vector2.ZERO
var network_ball_target_velocity := Vector2.ZERO
var network_ball_target_rotation := 0.0
var network_actor_target_positions := {-1: Vector2.ZERO, 1: Vector2.ZERO}


func setup(chosen_player: Dictionary, chosen_cpu: Dictionary, chosen_difficulty: String, chosen_bindings: Dictionary = {}) -> void:
	player_data = _normalized_character(chosen_player)
	cpu_data = _normalized_character(chosen_cpu)
	difficulty = chosen_difficulty
	bindings = chosen_bindings.duplicate(true)


func configure_network_server() -> void:
	runtime_mode = RuntimeMode.NETWORK_SERVER


func configure_network_client(side: int) -> void:
	runtime_mode = RuntimeMode.NETWORK_CLIENT
	local_network_side = -1 if side < 0 else 1


func _normalized_character(data: Dictionary) -> Dictionary:
	var result := data.duplicate(true)
	result.height = clampf(float(data.get("height", 1.0)), 0.8, 1.25)
	result.speed = clampf(float(data.get("speed", 1.0)), 0.8, 1.25)
	result.jump = clampf(float(data.get("jump", 1.0)), 0.8, 1.25)
	result.power = clampf(float(data.get("power", 1.0)), 0.8, 1.25)
	result.reach = clampf(float(data.get("reach", 1.0)), 0.8, 1.25)
	return result


func _ready() -> void:
	player = Actor.new(player_data, -1)
	cpu = Actor.new(cpu_data, 1)
	pause_button = Button.new()
	pause_button.text = "II"
	pause_button.tooltip_text = "暂停比赛"
	pause_button.position = PAUSE_BUTTON_RECT.position
	pause_button.size = PAUSE_BUTTON_RECT.size
	pause_button.theme_type_variation = &"GhostButton"
	pause_button.pivot_offset = pause_button.size * 0.5
	pause_button.pressed.connect(_toggle_pause)
	if runtime_mode != RuntimeMode.NETWORK_SERVER:
		add_child(pause_button)
	_prepare_serve(-1)
	set_process_input(runtime_mode != RuntimeMode.NETWORK_SERVER)


func _process(delta: float) -> void:
	if runtime_mode == RuntimeMode.NETWORK_CLIENT:
		_process_network_client(delta)
		return
	if game_paused or state == MatchState.MATCH_OVER:
		return
	var dt := minf(delta, 0.033)
	state_timer += dt
	action_message_time = maxf(0.0, action_message_time - dt)
	point_message_time = maxf(0.0, point_message_time - dt)
	screen_shake = move_toward(screen_shake, 0.0, dt * 24.0)
	score_pulse_time = maxf(0.0, score_pulse_time - dt)
	timing_flash_time = maxf(0.0, timing_flash_time - dt)
	net_clash_cooldown = maxf(0.0, net_clash_cooldown - dt)

	if runtime_mode == RuntimeMode.NETWORK_SERVER:
		_apply_network_inputs()
	else:
		_update_player_input()
		_update_ai(dt)
	_update_actor(player, dt)
	_update_actor(cpu, dt)
	if state == MatchState.PLAY:
		_update_prepared_spike(player)
		_update_prepared_spike(cpu)

	if state == MatchState.SERVE:
		_update_serve()
	elif state == MatchState.PLAY:
		_update_ball(dt)
		_update_normal_hit_window(player, dt)
		if not _check_net_clash():
			_check_block_contact(player)
			_check_block_contact(cpu)
			_check_dive_contact(player)
			_check_dive_contact(cpu)
	elif state == MatchState.POINT and state_timer >= 1.15:
		_advance_after_point()
	elif state == MatchState.SET_BREAK and state_timer >= 1.8:
		player_score = 0
		cpu_score = 0
		_prepare_serve(-serve_side)

	_update_particles(dt)
	queue_redraw()


func _update_player_input() -> void:
	if state == MatchState.SERVE:
		player.move_direction = 0.0
		player.fast_fall_held = false
		return
	var left := Input.is_physical_key_pressed(int(bindings.get("left", KEY_A)))
	var right := Input.is_physical_key_pressed(int(bindings.get("right", KEY_D)))
	var down := Input.is_physical_key_pressed(int(bindings.get("down", KEY_S)))
	player.move_direction = float(right) - float(left)
	player.fast_fall_held = down


func _apply_network_inputs() -> void:
	for actor in [player, cpu]:
		var input_state: Dictionary = network_inputs[actor.side]
		actor.move_direction = 0.0 if state == MatchState.SERVE else float(input_state.move)
		actor.fast_fall_held = false if state == MatchState.SERVE else bool(input_state.down)


func set_network_input(side: int, move_direction: float, fast_fall: bool) -> void:
	if runtime_mode != RuntimeMode.NETWORK_SERVER:
		return
	network_inputs[-1 if side < 0 else 1] = {"move": clampf(move_direction, -1.0, 1.0), "down": fast_fall}


func trigger_network_action(side: int, action: String) -> void:
	if runtime_mode != RuntimeMode.NETWORK_SERVER or game_paused:
		return
	var actor := player if side < 0 else cpu
	match action:
		"jump":
			_jump(actor)
		"hit":
			_normal_hit(actor)
		"spike":
			_start_spike(actor)
		"dive":
			_dive(actor)
		"block":
			_block(actor)


func _process_network_client(delta: float) -> void:
	var dt := minf(delta, 0.033)
	if not is_instance_valid(player) or not is_instance_valid(cpu):
		return
	var local_actor := player if local_network_side < 0 else cpu
	var left := Input.is_physical_key_pressed(int(bindings.get("left", KEY_A)))
	var right := Input.is_physical_key_pressed(int(bindings.get("right", KEY_D)))
	var down := Input.is_physical_key_pressed(int(bindings.get("down", KEY_S)))
	var move_direction := 0.0 if game_paused or state == MatchState.SERVE else float(right) - float(left)
	local_actor.move_direction = move_direction
	local_actor.fast_fall_held = down and not game_paused
	if not game_paused and state != MatchState.MATCH_OVER:
		_update_actor(local_actor, dt)
		var remote_actor := cpu if local_network_side < 0 else player
		_update_actor(remote_actor, dt)
		_correct_remote_actor(remote_actor, dt)
		if state == MatchState.PLAY:
			previous_ball_position = ball_position
			ball_velocity.y += GRAVITY * dt
			ball_position += ball_velocity * dt
			ball_rotation += ball_velocity.x * dt * 0.015
			_correct_network_ball(dt)
	network_send_accumulator += dt
	if network_send_accumulator >= NETWORK_INPUT_INTERVAL:
		network_send_accumulator = fmod(network_send_accumulator, NETWORK_INPUT_INTERVAL)
		network_input_sequence += 1
		var bridge := get_node_or_null("/root/NetworkBridge")
		if bridge:
			bridge.send_input_state(network_input_sequence, move_direction, down and not game_paused)
	_update_particles(dt)
	queue_redraw()


func _update_particles(delta: float) -> void:
	for index in range(particles.size() - 1, -1, -1):
		var particle: Dictionary = particles[index]
		particle.life -= delta
		particle.position += particle.velocity * delta
		particle.velocity.y += float(particle.get("gravity", 620.0)) * delta
		if float(particle.life) <= 0.0:
			particles.remove_at(index)


func _correct_network_ball(delta: float) -> void:
	if not network_snapshot_ready:
		return
	var distance := ball_position.distance_to(network_ball_target_position)
	var weight := 1.0 if distance >= NETWORK_SNAP_DISTANCE else 1.0 - exp(-NETWORK_CORRECTION_RATE * delta)
	ball_position = ball_position.lerp(network_ball_target_position, weight)
	ball_velocity = ball_velocity.lerp(network_ball_target_velocity, minf(1.0, weight * 1.35))
	ball_rotation = lerp_angle(ball_rotation, network_ball_target_rotation, weight)


func _correct_remote_actor(actor: Actor, delta: float) -> void:
	if not network_snapshot_ready:
		return
	var target: Vector2 = network_actor_target_positions[actor.side]
	var distance := actor.position.distance_to(target)
	var weight := 1.0 if distance >= NETWORK_SNAP_DISTANCE else 1.0 - exp(-NETWORK_CORRECTION_RATE * delta)
	actor.position = actor.position.lerp(target, weight)


func _update_actor(actor: Actor, delta: float) -> void:
	actor.animation_clock += delta * (0.9 + float(actor.data.speed) * 0.22)
	var had_spike_window := actor.spike_ready_time > 0.0
	var had_block_window := actor.block_time > 0.0
	actor.hit_cooldown = maxf(0.0, actor.hit_cooldown - delta)
	actor.action_lock_time = maxf(0.0, actor.action_lock_time - delta)
	actor.dive_time = maxf(0.0, actor.dive_time - delta)
	actor.block_time = maxf(0.0, actor.block_time - delta)
	actor.block_cooldown = maxf(0.0, actor.block_cooldown - delta)
	actor.bump_time = maxf(0.0, actor.bump_time - delta)
	actor.flash_time = maxf(0.0, actor.flash_time - delta)
	actor.jump_time = maxf(0.0, actor.jump_time - delta)
	actor.land_time = maxf(0.0, actor.land_time - delta)
	actor.set_time = maxf(0.0, actor.set_time - delta)
	actor.spike_time = maxf(0.0, actor.spike_time - delta)
	actor.spike_ready_time = maxf(0.0, actor.spike_ready_time - delta)
	actor.spike_lunge_time = maxf(0.0, actor.spike_lunge_time - delta)
	actor.serve_time = maxf(0.0, actor.serve_time - delta)
	actor.hurt_time = maxf(0.0, actor.hurt_time - delta)
	actor.celebrate_time = maxf(0.0, actor.celebrate_time - delta)
	actor.turn_time = maxf(0.0, actor.turn_time - delta)
	actor.dust_cooldown = maxf(0.0, actor.dust_cooldown - delta)
	if had_spike_window and actor.spike_ready_time <= 0.0 and actor.spike_attempted:
		actor.spike_attempted = false
		actor.k_attack = KAttack.NONE
		actor.hit_cooldown = maxf(actor.hit_cooldown, 0.42)
		actor.action_lock_time = maxf(actor.action_lock_time, 0.24)
		if actor == player:
			_show_action("招式挥空", COLORS.muted)
	if had_block_window and actor.block_time <= 0.0 and not actor.block_connected:
		actor.hit_cooldown = maxf(actor.hit_cooldown, 0.38)
		actor.action_lock_time = maxf(actor.action_lock_time, 0.38)
		if actor == player:
			_show_action("拦网落空", COLORS.muted)
	var intended_sign := signf(actor.move_direction)
	if intended_sign != 0.0:
		if actor.last_move_sign != 0.0 and actor.last_move_sign != intended_sign:
			actor.turn_time = 0.16
		actor.last_move_sign = intended_sign
	var speed := 280.0 * float(actor.data.speed)
	if actor.dive_time > 0.0:
		actor.velocity.x = actor.dive_direction * 460.0 * float(actor.data.speed)
	elif actor.spike_lunge_time > 0.0:
		actor.velocity.x = actor.spike_move_direction * (150.0 + actor.spike_charge * 90.0)
	else:
		var movement := actor.move_direction if actor.action_lock_time <= 0.0 else 0.0
		actor.velocity.x = move_toward(actor.velocity.x, movement * speed, delta * 1600.0)
	var was_grounded := actor.grounded
	if not actor.grounded:
		var gravity := 2400.0 if actor.fast_fall_held and actor.velocity.y > -120.0 else 1450.0
		actor.velocity.y += gravity * delta
	actor.position += actor.velocity * delta
	var min_x := 58.0 if actor.side < 0 else NET_X + 34.0
	var max_x := NET_X - 34.0 if actor.side < 0 else 902.0
	actor.position.x = clampf(actor.position.x, min_x, max_x)
	if actor.position.y >= FLOOR_Y:
		actor.position.y = FLOOR_Y
		actor.velocity.y = 0.0
		actor.grounded = true
		if not was_grounded:
			actor.land_time = 0.24
			actor.spike_ready_time = 0.0
			actor.spike_lunge_time = 0.0
			actor.spike_attempted = false
			_spawn_dust(actor.position, actor.color(), 10)
	if actor.grounded and absf(actor.velocity.x) > 120.0 and actor.dust_cooldown <= 0.0:
		actor.dust_cooldown = 0.16 / maxf(0.75, float(actor.data.speed))
		_spawn_dust(actor.position + Vector2(-signf(actor.velocity.x) * 10.0, -2.0), actor.color(), 3)


func _jump(actor: Actor) -> void:
	if not actor.grounded or state != MatchState.PLAY:
		return
	actor.velocity.y = -760.0 * float(actor.data.jump)
	actor.grounded = false
	actor.jump_time = 0.18
	actor.land_time = 0.0
	_spawn_dust(actor.position, actor.color(), 7)


func _dive(actor: Actor) -> void:
	if not actor.grounded or actor.dive_time > 0.0 or state != MatchState.PLAY:
		return
	actor.dive_time = 0.34
	actor.dive_direction = actor.move_direction
	if actor.dive_direction == 0.0:
		actor.dive_direction = signf(ball_position.x - actor.position.x)
	if actor.dive_direction == 0.0:
		actor.dive_direction = -actor.side
	actor.dive_has_hit = false
	_spawn_dust(actor.position, actor.color(), 12)
	_spawn_speed_lines(actor.position + Vector2(actor.dive_direction * 20.0, -38.0), actor.dive_direction, actor.color(), 5)
	_show_action("飞身救球", COLORS.cyan)


func _block(actor: Actor) -> void:
	if state != MatchState.PLAY or actor.block_time > 0.0 or actor.action_lock_time > 0.0:
		return
	if actor.block_cooldown > 0.0:
		if actor == player:
			_show_action("拦网冷却", COLORS.muted)
		return
	var fatigue_scale := maxf(0.52, 1.0 - float(actor.block_fatigue) * 0.16)
	actor.block_time = 0.38
	actor.block_cooldown = BLOCK_COOLDOWN
	actor.block_reach_scale = fatigue_scale
	actor.block_connected = false
	actor.block_fatigue = mini(3, actor.block_fatigue + 1)
	if actor.grounded:
		_jump(actor)
	actor.velocity.x = -actor.side * 120.0
	_show_action("拦网", actor.color())


func _normal_hit(actor: Actor) -> void:
	if actor.hit_cooldown > 0.0 or actor.action_lock_time > 0.0 or actor.normal_hit_window > 0.0:
		return
	if state == MatchState.SERVE:
		if serve_side == actor.side:
			_serve_ball(actor, 0.45)
		return
	if state != MatchState.PLAY:
		return
	var phase := _next_touch_phase(actor)
	if phase in [TouchPhase.RECEIVE, TouchPhase.SET] and not actor.grounded:
		if actor == player:
			_show_action("落地后垫球" if phase == TouchPhase.RECEIVE else "落地后二传", COLORS.muted)
		return
	if phase == TouchPhase.ATTACK and not actor.grounded:
		_start_spike(actor, KAttack.AIR_SPIKE, 0.0, ShotRoute.TIP)
		return
	actor.normal_hit_mode = "receive" if phase == TouchPhase.RECEIVE else ("set" if phase == TouchPhase.SET else "save")
	actor.normal_hit_window = NORMAL_HIT_WINDOW
	actor.action_lock_time = maxf(actor.action_lock_time, NORMAL_HIT_WINDOW)
	if phase == TouchPhase.RECEIVE:
		actor.bump_time = maxf(actor.bump_time, 0.22)
	_update_normal_hit_window(actor, 0.0)


func _update_normal_hit_window(actor: Actor, delta: float) -> void:
	if actor.normal_hit_window <= 0.0:
		return
	if state != MatchState.PLAY:
		actor.normal_hit_window = 0.0
		return
	actor.normal_hit_window = maxf(0.0, actor.normal_hit_window - delta)
	var contact := _contact_geometry(actor, _next_touch_phase(actor))
	if bool(contact.valid):
		var hit_mode := actor.normal_hit_mode
		actor.normal_hit_window = 0.0
		_perform_hit(actor, 0.35, hit_mode, float(contact.quality))
	elif actor.normal_hit_window <= 0.0:
		actor.hit_cooldown = maxf(actor.hit_cooldown, 0.18)
		actor.action_lock_time = maxf(actor.action_lock_time, 0.08)


func _start_spike(actor: Actor, forced_attack: int = KAttack.NONE, forced_direction: float = 0.0, forced_route: int = -1) -> void:
	if state != MatchState.PLAY or actor.hit_cooldown > 0.0 or actor.action_lock_time > 0.0 or actor.spike_ready_time > 0.0:
		return
	var phase := _next_touch_phase(actor)
	if phase != TouchPhase.ATTACK:
		if actor == player:
			_show_action("先垫球" if phase == TouchPhase.RECEIVE else ("先二传" if phase == TouchPhase.SET else "本方触球已用尽"), COLORS.muted)
		return
	if actor.grounded:
		if actor == player:
			_show_action("先起跳扣球", COLORS.muted)
		return
	var direction := forced_direction if forced_direction != 0.0 else _action_direction(actor)
	var down_held := _action_down_held(actor)
	var attack := forced_attack
	if attack == KAttack.NONE:
		if actor.grounded:
			attack = KAttack.GROUND_DOWN if down_held else (KAttack.GROUND_SIDE if direction != 0.0 else KAttack.GROUND_UP)
		else:
			attack = KAttack.AIR_SPIKE
	actor.spike_charge = 0.72
	actor.spike_attempted = true
	actor.k_attack = attack
	actor.k_attack_direction = direction
	actor.shot_route = forced_route if forced_route >= 0 else (ShotRoute.CROSS if direction != 0.0 else ShotRoute.STRAIGHT)
	actor.spike_ready_time = K_ATTACK_WINDOW
	actor.spike_lunge_time = 0.0
	var facing := 1.0 if actor.side < 0 else -1.0
	match attack:
		KAttack.GROUND_UP:
			actor.velocity.y = -400.0 * float(actor.data.jump)
			actor.grounded = false
			actor.jump_time = 0.14
			actor.spike_move_direction = 0.0
			_spawn_dust(actor.position, actor.color(), 7)
		KAttack.GROUND_SIDE:
			actor.spike_move_direction = direction if direction != 0.0 else facing
			actor.spike_lunge_time = 0.22
			actor.velocity.x = actor.spike_move_direction * 255.0
		KAttack.GROUND_DOWN:
			actor.spike_move_direction = 0.0
			actor.velocity.x = 0.0
		_:
			actor.spike_move_direction = 0.0
	_spawn_speed_lines(actor.position + Vector2(0, -45), facing, actor.color(), 5)
	var attack_name := _k_attack_name(attack, actor.shot_route)
	_show_action(attack_name, COLORS.red)
	_update_prepared_spike(actor)


func _launch_prepared_spike(actor: Actor, _charge: float = 0.72) -> void:
	_start_spike(actor)


func _update_prepared_spike(actor: Actor) -> void:
	if actor.spike_ready_time <= 0.0 or state != MatchState.PLAY:
		return
	var contact := _contact_geometry(actor, TouchPhase.ATTACK)
	var quality := float(contact.quality)
	if not bool(contact.valid):
		return
	if _perform_k_attack(actor, quality):
		actor.spike_ready_time = 0.0
		actor.spike_attempted = false
		actor.k_attack = KAttack.NONE
		actor.spike_lunge_time = minf(actor.spike_lunge_time, 0.12)


func _action_direction(actor: Actor) -> float:
	if actor == player:
		var left := Input.is_physical_key_pressed(int(bindings.get("left", KEY_A)))
		var right := Input.is_physical_key_pressed(int(bindings.get("right", KEY_D)))
		var live_direction := float(right) - float(left)
		if live_direction != 0.0:
			return live_direction
	return actor.move_direction


func _action_down_held(actor: Actor) -> bool:
	if actor == player:
		return Input.is_physical_key_pressed(int(bindings.get("down", KEY_S)))
	return actor.fast_fall_held


func _action_jump_held(actor: Actor) -> bool:
	if actor == player:
		return Input.is_physical_key_pressed(int(bindings.get("jump", KEY_W)))
	return false


func _next_touch_phase(actor: Actor) -> int:
	if last_touch_side != actor.side:
		return TouchPhase.RECEIVE
	if side_touches == 1:
		return TouchPhase.SET
	if side_touches == 2:
		return TouchPhase.ATTACK
	return -1


func _ideal_contact_position(actor: Actor, phase: int) -> Vector2:
	var facing := 1.0 if actor.side < 0 else -1.0
	var height := float(actor.data.height)
	var reach := float(actor.data.reach)
	if phase == TouchPhase.RECEIVE:
		return actor.position + Vector2(facing * 38.0 * reach, -46.0 * height)
	if phase == TouchPhase.SET:
		return actor.position + Vector2(0.0, -98.0 * height)
	return actor.position + Vector2(facing * 36.0 * reach, -88.0 * height)


func _contact_geometry(actor: Actor, phase: int) -> Dictionary:
	if phase < 0:
		return {"valid": false, "quality": 0.0}
	var ideal := _ideal_contact_position(actor, phase)
	var contact_position := ball_position
	var path := ball_position - previous_ball_position
	if path.length_squared() > 0.001 and path.length_squared() <= 120.0 * 120.0:
		var path_ratio := clampf((ideal - previous_ball_position).dot(path) / path.length_squared(), 0.0, 1.0)
		contact_position = previous_ball_position + path * path_ratio
	var max_distance := (88.0 if phase == TouchPhase.RECEIVE else (84.0 if phase == TouchPhase.SET else 96.0)) * float(actor.data.reach)
	var distance_score := clampf(1.0 - ideal.distance_to(contact_position) / max_distance, 0.0, 1.0)
	var ideal_vector := (ideal - actor.position).normalized()
	var actual_vector := (contact_position - actor.position).normalized()
	var angle := acos(clampf(ideal_vector.dot(actual_vector), -1.0, 1.0))
	var max_angle := deg_to_rad(52.0 if phase == TouchPhase.RECEIVE else (50.0 if phase == TouchPhase.SET else 54.0))
	var angle_score := clampf(1.0 - angle / max_angle, 0.0, 1.0)
	return {"valid": distance_score > 0.0 and angle_score > 0.0, "quality": sqrt(distance_score * angle_score), "position": contact_position}


func _k_attack_name(attack: int, shot_route: int = ShotRoute.STRAIGHT) -> String:
	match attack:
		KAttack.GROUND_UP:
			return "上挑击球"
		KAttack.GROUND_SIDE:
			return "突进击球"
		KAttack.GROUND_DOWN:
			return "低身击球"
		KAttack.AIR_SPIKE:
			return "直线重扣" if shot_route == ShotRoute.STRAIGHT else ("斜线扣球" if shot_route == ShotRoute.CROSS else "吊球")
		_:
			return "重扣"


func _k_attack_contact_position(actor: Actor) -> Vector2:
	var facing := 1.0 if actor.side < 0 else -1.0
	var height := float(actor.data.height)
	var reach := float(actor.data.reach)
	match actor.k_attack:
		KAttack.GROUND_UP:
			return actor.position + Vector2(0.0, -102.0 * height)
		KAttack.GROUND_SIDE:
			return actor.position + Vector2(actor.k_attack_direction * 54.0 * reach, -55.0 * height)
		KAttack.GROUND_DOWN:
			return actor.position + Vector2(facing * 35.0 * reach, -30.0 * height)
		_:
			return _ideal_contact_position(actor, TouchPhase.ATTACK)


func _k_attack_reach(actor: Actor) -> float:
	var base_reach := 96.0
	match actor.k_attack:
		KAttack.GROUND_DOWN:
			base_reach = 68.0
		KAttack.GROUND_UP, KAttack.GROUND_SIDE:
			base_reach = 74.0
	return base_reach


func _perform_k_attack(actor: Actor, contact_quality: float = 1.0) -> bool:
	if _next_touch_phase(actor) != TouchPhase.ATTACK:
		return false
	if actor.side == serve_touch_locked_side:
		return false
	if actor == player:
		last_player_attack_from_backcourt = actor.k_attack == KAttack.AIR_SPIKE and actor.shot_route != ShotRoute.TIP and _is_backcourt_spike(actor)
	_register_touch(actor.side)
	if state != MatchState.PLAY:
		return false
	var facing := 1.0 if actor.side < 0 else -1.0
	var power := float(actor.data.power)
	match actor.k_attack:
		KAttack.GROUND_UP:
			ball_velocity = Vector2(facing * 95.0, -790.0)
		KAttack.GROUND_SIDE:
			var direction := actor.k_attack_direction if actor.k_attack_direction != 0.0 else facing
			ball_velocity = Vector2(direction * 620.0 * power, -330.0)
		KAttack.GROUND_DOWN:
			ball_velocity = Vector2(facing * 460.0 * power, -560.0)
		KAttack.AIR_SPIKE:
			ball_velocity = _air_spike_velocity(actor, contact_quality)
		_:
			ball_velocity = _attack_velocity(actor, actor.spike_charge)
	actor.spike_time = 0.42
	actor.flash_time = 0.1
	actor.hit_cooldown = 0.18
	actor.action_lock_time = maxf(actor.action_lock_time, 0.1)
	if actor.shot_route != ShotRoute.TIP:
		spikes += 1 if actor.side < 0 else 0
	var attack_name := _k_attack_name(actor.k_attack, actor.shot_route)
	_register_quality_feedback(actor, contact_quality, 95 if actor.shot_route != ShotRoute.TIP else 70)
	_show_action(_quality_label(contact_quality, "完美" + attack_name, attack_name, "勉强" + attack_name), COLORS.cyan if actor.shot_route == ShotRoute.TIP else COLORS.red)
	screen_shake = (3.0 if actor.shot_route == ShotRoute.TIP else (5.0 if actor.shot_route == ShotRoute.CROSS else 7.0)) * lerpf(0.65, 1.0, contact_quality)
	_spawn_impact(ball_position, actor.color(), actor.shot_route != ShotRoute.TIP and contact_quality >= 0.32)
	_spawn_speed_lines(ball_position, signf(ball_velocity.x) if ball_velocity.x != 0.0 else facing, actor.color(), 8)
	return true


func _serve_ball(actor: Actor, charge: float) -> void:
	if actor == player:
		last_player_attack_from_backcourt = false
	state = MatchState.PLAY
	state_timer = 0.0
	last_touch_side = actor.side
	side_touches = 1
	rally_hits = 1
	serve_touch_locked_side = actor.side
	actor.serve_time = 0.42
	actor.flash_time = 0.08
	var across := 1.0 if actor.side < 0 else -1.0
	ball_velocity = Vector2(across * (330.0 + charge * 160.0) * sqrt(float(actor.data.power)), -610.0 - charge * 90.0)
	_spawn_impact(ball_position, actor.color(), charge > 0.65)
	_spawn_speed_lines(ball_position, across, actor.color(), 7 if charge > 0.65 else 4)
	_show_action("强力发球" if charge > 0.65 else "发球", actor.color())


func _perform_hit(actor: Actor, charge: float, ai_mode: String = "", contact_quality: float = -1.0) -> bool:
	if actor.hit_cooldown > 0.0:
		return false
	if actor.side == serve_touch_locked_side:
		actor.hit_cooldown = 0.12
		if actor == player:
			_show_action("等待对方触球", COLORS.muted)
		return false
	var phase := _next_touch_phase(actor)
	if phase < 0:
		if actor == player:
			_show_action("本方触球已用尽", COLORS.muted)
		return false
	if phase in [TouchPhase.RECEIVE, TouchPhase.SET] and not actor.grounded:
		if actor == player:
			_show_action("落地后垫球" if phase == TouchPhase.RECEIVE else "落地后二传", COLORS.muted)
		return false
	if actor == player:
		last_player_attack_from_backcourt = false
	var contact := _contact_geometry(actor, phase)
	if contact_quality < 0.0:
		if not bool(contact.valid):
			actor.hit_cooldown = 0.32
			actor.action_lock_time = maxf(actor.action_lock_time, 0.18)
			return false
		contact_quality = float(contact.quality)
	ball_position = Vector2(contact.get("position", ball_position))
	_register_touch(actor.side)
	if state != MatchState.PLAY:
		return false
	actor.hit_cooldown = 0.14
	actor.action_lock_time = maxf(actor.action_lock_time, 0.08)
	var across := 1.0 if actor.side < 0 else -1.0
	var quality := clampf(float(contact.quality) if contact_quality < 0.0 else contact_quality, 0.0, 1.0)
	var airborne_attack := not actor.grounded and ball_position.y < actor.position.y - 28.0
	if ai_mode == "set" or phase == TouchPhase.SET:
		ball_velocity = _self_set_velocity(actor, quality)
		actor.set_time = 0.34
		actor.block_fatigue = 0
		_show_action(_quality_label(quality, "精准二传", "二传", "勉强二传"), COLORS.yellow)
	elif ai_mode == "receive" or phase == TouchPhase.RECEIVE:
		ball_velocity = _receive_velocity(actor, quality)
		actor.bump_time = 0.34
		actor.block_fatigue = 0
		_show_action(_quality_label(quality, "完美垫球", "垫球", "勉强垫球"), actor.color())
	elif ai_mode == "spike" or (airborne_attack and actor != player):
		ball_velocity = _attack_velocity(actor, charge)
		actor.spike_time = 0.42
		actor.flash_time = 0.1
		actor.hit_cooldown = 0.18
		actor.action_lock_time = maxf(actor.action_lock_time, 0.1)
		spikes += 1 if actor.side < 0 else 0
		_show_action("重扣", COLORS.red)
		screen_shake = 7.0
	else:
		ball_velocity = Vector2(across * (330.0 + quality * 90.0), -710.0 - quality * 90.0)
		actor.bump_time = 0.34
		actor.block_fatigue = 0
		_show_action(_quality_label(quality, "精准保命", "保命回球", "勉强回球"), actor.color())
	var quality_points := 50 if phase == TouchPhase.RECEIVE else (60 if phase == TouchPhase.SET else 80)
	_register_quality_feedback(actor, quality, quality_points)
	_spawn_impact(ball_position, actor.color(), charge > 0.65)
	_spawn_speed_lines(ball_position, across, actor.color(), 8 if actor.spike_time > 0.0 else 4)
	return true


func _quality_label(quality: float, perfect: String, good: String, poor: String) -> String:
	return perfect if quality >= PERFECT_TIMING_QUALITY else (good if quality >= 0.32 else poor)


func _register_quality_feedback(actor: Actor, quality: float, base_points: int) -> void:
	if actor != player:
		return
	if quality >= PERFECT_TIMING_QUALITY:
		current_combo += 1
		perfect_touches += 1
		max_combo = maxi(max_combo, current_combo)
		performance_score += base_points + mini(current_combo, 8) * 12
	elif quality >= 0.32:
		current_combo = 0
		performance_score += int(base_points / 2.0)
	else:
		current_combo = 0
		performance_score += 10


func _self_set_velocity(actor: Actor, quality: float) -> Vector2:
	var timing := clampf(quality, 0.0, 1.0)
	var direction := _contact_deflection_direction(actor)
	var target_distance := lerpf(90.0, 8.0, timing)
	var target_x := actor.position.x + direction * target_distance
	var vertical_speed := lerpf(-540.0, -660.0, timing)
	var time_to_apex := -vertical_speed / GRAVITY
	return Vector2((target_x - ball_position.x) / time_to_apex, vertical_speed)


func _receive_velocity(actor: Actor, quality: float) -> Vector2:
	var timing := clampf(quality, 0.0, 1.0)
	var direction := _contact_deflection_direction(actor)
	var target_distance := lerpf(110.0, 35.0, timing)
	var target_x := actor.position.x + direction * target_distance
	var vertical_speed := lerpf(-500.0, -620.0, timing)
	var time_to_apex := -vertical_speed / GRAVITY
	return Vector2((target_x - ball_position.x) / time_to_apex, vertical_speed)


func _contact_deflection_direction(actor: Actor) -> float:
	var horizontal_offset := ball_position.x - actor.position.x
	if absf(horizontal_offset) >= 8.0:
		return signf(horizontal_offset)
	return 1.0 if actor.side < 0 else -1.0


func _attack_velocity(actor: Actor, charge: float) -> Vector2:
	var across := 1.0 if actor.side < 0 else -1.0
	var horizontal_speed := (420.0 + charge * 250.0) * float(actor.data.power)
	var distance_to_net := absf(ball_position.x - NET_X)
	var high_contact := ball_position.y + BALL_RADIUS <= NET_TOP - 24.0
	if distance_to_net <= 72.0 and high_contact:
		return Vector2(across * horizontal_speed, (105.0 + charge * 165.0) * float(actor.data.power))

	# Aim safely above the tape so attacks from the back court still cross the net.
	var travel_time := maxf(0.08, distance_to_net / horizontal_speed)
	var target_y := NET_TOP - BALL_RADIUS - 24.0
	var vertical_speed := (target_y - ball_position.y - 0.5 * GRAVITY * travel_time * travel_time) / travel_time
	return Vector2(across * horizontal_speed, clampf(vertical_speed, -760.0, -220.0))


func _air_spike_velocity(actor: Actor, contact_quality: float = 1.0) -> Vector2:
	var target_x: float
	var target_distance := 365.0 if actor.shot_route == ShotRoute.STRAIGHT else (250.0 if actor.shot_route == ShotRoute.CROSS else 100.0)
	if actor.side < 0:
		target_x = NET_X + target_distance
	else:
		target_x = NET_X - target_distance
	if actor.shot_route != ShotRoute.TIP:
		var quality := clampf(contact_quality, 0.0, 1.0)
		var power := float(actor.data.power)
		if _is_backcourt_spike(actor):
			return _backcourt_spike_velocity(target_x, quality, power)
		var speed := lerpf(420.0, 850.0, quality) * power
		if actor.shot_route == ShotRoute.CROSS:
			speed *= 0.82
		var velocity_x := signf(target_x - ball_position.x) * speed
		var downward_speed := lerpf(90.0, 420.0, quality) * power
		var time_to_net := (NET_X - ball_position.x) / velocity_x
		if time_to_net > 0.0:
			var net_clearance_y := NET_TOP - BALL_RADIUS - 8.0
			var max_downward_speed := (net_clearance_y - ball_position.y - 0.5 * GRAVITY * time_to_net * time_to_net) / time_to_net
			downward_speed = minf(downward_speed, maxf(35.0, max_downward_speed))
		return Vector2(
			velocity_x,
			downward_speed
		)
	var flight_time := 0.82 if actor.shot_route == ShotRoute.STRAIGHT else (0.96 if actor.shot_route == ShotRoute.CROSS else 1.18)
	flight_time += (1.0 - clampf(contact_quality, 0.0, 1.0)) * 0.18
	var horizontal_distance := target_x - ball_position.x
	if horizontal_distance != 0.0 and (NET_X - ball_position.x) / horizontal_distance > 0.0 and (NET_X - ball_position.x) / horizontal_distance < 1.0:
		var net_ratio := (NET_X - ball_position.x) / horizontal_distance
		var net_clearance_y := NET_TOP - BALL_RADIUS - 10.0
		while flight_time < 1.6:
			var net_time := net_ratio * flight_time
			var test_velocity_y := (FLOOR_Y - BALL_RADIUS - ball_position.y - 0.5 * GRAVITY * flight_time * flight_time) / flight_time
			var predicted_net_y := ball_position.y + test_velocity_y * net_time + 0.5 * GRAVITY * net_time * net_time
			if predicted_net_y <= net_clearance_y:
				break
			flight_time += 0.05
	var velocity_x := (target_x - ball_position.x) / flight_time
	var landing_y := FLOOR_Y - BALL_RADIUS
	var velocity_y := (landing_y - ball_position.y - 0.5 * GRAVITY * flight_time * flight_time) / flight_time
	if ball_position.y + BALL_RADIUS > NET_TOP - 30.0:
		velocity_y = minf(velocity_y, -300.0 if actor.shot_route == ShotRoute.TIP else -420.0)
	return Vector2(velocity_x, velocity_y)


func _is_backcourt_spike(actor: Actor) -> bool:
	return absf(actor.position.x - NET_X) > BACKCOURT_SPIKE_DISTANCE


func _backcourt_spike_velocity(target_x: float, quality: float, power: float) -> Vector2:
	var landing_y := FLOOR_Y - BALL_RADIUS
	var attack_direction := signf(target_x - NET_X)
	target_x = clampf(
		target_x + attack_direction * (power - 1.0) * 300.0,
		LEFT_LIMIT + BALL_RADIUS,
		RIGHT_LIMIT - BALL_RADIUS
	)
	var horizontal_distance := target_x - ball_position.x
	var minimum_arc_time := sqrt(maxf(0.0, 2.0 * (landing_y - ball_position.y) / GRAVITY)) + 0.08
	var flight_time := maxf(minimum_arc_time, lerpf(1.18, 0.82, quality) / sqrt(power))
	var net_ratio := (NET_X - ball_position.x) / horizontal_distance if horizontal_distance != 0.0 else -1.0
	if net_ratio > 0.0 and net_ratio < 1.0:
		var net_clearance_y := NET_TOP - BALL_RADIUS - 12.0
		while flight_time < 1.65:
			var velocity_y := (landing_y - ball_position.y - 0.5 * GRAVITY * flight_time * flight_time) / flight_time
			var net_time := net_ratio * flight_time
			var predicted_net_y := ball_position.y + velocity_y * net_time + 0.5 * GRAVITY * net_time * net_time
			if predicted_net_y <= net_clearance_y:
				break
			flight_time += 0.04
	var velocity_x := horizontal_distance / flight_time
	var velocity_y := (landing_y - ball_position.y - 0.5 * GRAVITY * flight_time * flight_time) / flight_time
	return Vector2(velocity_x, velocity_y)


func _register_touch(side: int) -> void:
	if serve_touch_locked_side != 0 and side != serve_touch_locked_side:
		serve_touch_locked_side = 0
	if last_touch_side == side:
		side_touches += 1
	else:
		last_touch_side = side
		side_touches = 1
	rally_hits += 1
	if side_touches > 3:
		_award_point(-side, "触球超过三次")


func _check_net_clash() -> bool:
	if net_clash_cooldown > 0.0 or absf(ball_position.x - NET_X) > 92.0 or ball_position.y > NET_TOP + 34.0:
		return false
	if not _actor_has_net_contact(player) or not _actor_has_net_contact(cpu):
		return false
	net_clash_cooldown = 0.32
	last_touch_side = 0
	side_touches = 0
	rally_hits += 1
	player.spike_ready_time = 0.0
	cpu.spike_ready_time = 0.0
	player.spike_attempted = false
	cpu.spike_attempted = false
	player.block_time = 0.0
	cpu.block_time = 0.0
	player.hit_cooldown = 0.3
	cpu.hit_cooldown = 0.3
	player.velocity.x = -220.0
	cpu.velocity.x = 220.0
	ball_position.y = minf(ball_position.y, NET_TOP - BALL_RADIUS - 24.0)
	var horizontal_sign := signf(ball_velocity.x)
	if horizontal_sign == 0.0:
		horizontal_sign = -1.0 if randf() < 0.5 else 1.0
	ball_velocity = Vector2(-horizontal_sign * 105.0, -535.0)
	screen_shake = 8.0
	_show_action("网口对抗", COLORS.yellow)
	_spawn_impact(ball_position, COLORS.yellow, true)
	return true


func _actor_has_net_contact(actor: Actor) -> bool:
	var spike_reach := _k_attack_reach(actor) * float(actor.data.reach)
	var spike_contact := actor.spike_ready_time > 0.0 and _k_attack_contact_position(actor).distance_to(ball_position) <= spike_reach
	var block_hand := _block_contact_position(actor)
	var block_contact := actor.block_time > 0.0 and block_hand.distance_to(ball_position) <= 58.0 * float(actor.data.reach) * actor.block_reach_scale
	return spike_contact or block_contact


func _block_contact_position(actor: Actor) -> Vector2:
	return actor.position + Vector2(0.0, -113.0 * float(actor.data.height))


func _check_block_contact(actor: Actor) -> void:
	if actor.block_time <= 0.0 or actor.hit_cooldown > 0.0:
		return
	var hand := _block_contact_position(actor)
	if hand.distance_to(ball_position) > 58.0 * float(actor.data.reach) * actor.block_reach_scale:
		return
	if actor.side == serve_touch_locked_side:
		return
	if serve_touch_locked_side != 0 and actor.side != serve_touch_locked_side:
		serve_touch_locked_side = 0
	last_touch_side = actor.side
	side_touches = 0
	rally_hits += 1
	actor.block_connected = true
	actor.block_time = 0.0
	actor.hit_cooldown = 0.12
	actor.action_lock_time = maxf(actor.action_lock_time, 0.12)
	ball_position.y = minf(ball_position.y, NET_TOP - BALL_RADIUS - 20.0)
	ball_velocity.x = -actor.side * 430.0
	ball_velocity.y = 100.0
	blocks += 1 if actor.side < 0 else 0
	if actor == player:
		performance_score += 160
	_show_action("拦网", actor.color())
	_spawn_impact(ball_position, actor.color(), true)


func _check_dive_contact(actor: Actor) -> void:
	if actor.dive_time <= 0.0 or actor.dive_has_hit:
		return
	var contact := _dive_contact_position(actor)
	if contact.distance_to(ball_position) > 78.0 * float(actor.data.reach):
		return
	if actor.side == serve_touch_locked_side:
		return
	_register_touch(actor.side)
	if state != MatchState.PLAY:
		return
	actor.dive_has_hit = true
	actor.hit_cooldown = 0.28
	ball_velocity = Vector2(-actor.side * 125.0, -625.0)
	saves += 1 if actor.side < 0 else 0
	if actor == player:
		performance_score += 120
	_show_action("救球成功", COLORS.cyan)
	_spawn_impact(ball_position, actor.color(), true)


func _dive_contact_position(actor: Actor) -> Vector2:
	var direction := actor.dive_direction
	if direction == 0.0:
		direction = -actor.side
	return actor.position + Vector2(direction * 48.0 * float(actor.data.reach), -34.0 * float(actor.data.height))


func _update_serve() -> void:
	var server := player if serve_side < 0 else cpu
	ball_position = server.position + Vector2(
		(30.0 if serve_side < 0 else -30.0) * float(server.data.reach),
		-82.0 * float(server.data.height)
	)
	ball_velocity = Vector2.ZERO
	if runtime_mode == RuntimeMode.NETWORK_SERVER:
		return
	if serve_side > 0 and state_timer >= _ai_reaction() + 0.55:
		_serve_ball(cpu, 0.45 + _ai_skill() * 0.35)
	elif serve_side < 0 and state_timer >= 4.0:
		_serve_ball(player, 0.35)


func _update_ball(delta: float) -> void:
	previous_ball_position = ball_position
	ball_velocity.y += GRAVITY * delta
	ball_position += ball_velocity * delta
	ball_rotation += ball_velocity.x * delta * 0.015
	if ball_position.x - BALL_RADIUS <= LEFT_LIMIT:
		ball_position.x = LEFT_LIMIT + BALL_RADIUS
		ball_velocity.x = absf(ball_velocity.x) * 0.7
	if ball_position.x + BALL_RADIUS >= RIGHT_LIMIT:
		ball_position.x = RIGHT_LIMIT - BALL_RADIUS
		ball_velocity.x = -absf(ball_velocity.x) * 0.7
	_check_net_collision()
	if ball_position.y + BALL_RADIUS >= FLOOR_Y:
		ball_position.y = FLOOR_Y - BALL_RADIUS
		_award_point(1 if ball_position.x < NET_X else -1, "球落地")
	elif ball_position.y < BALL_CEILING_Y:
		ball_position.y = BALL_CEILING_Y
		ball_velocity.y = absf(ball_velocity.y) * 0.65


func _check_net_collision() -> void:
	if ball_position.y + BALL_RADIUS < NET_TOP:
		return
	var crossed := (previous_ball_position.x < NET_X and ball_position.x >= NET_X) or (previous_ball_position.x > NET_X and ball_position.x <= NET_X)
	if crossed:
		ball_position.x = NET_X - BALL_RADIUS - 3.0 if previous_ball_position.x < NET_X else NET_X + BALL_RADIUS + 3.0
		ball_velocity.x *= -0.62
		ball_velocity.y *= 0.85
		_show_action("触网", COLORS.paper)
	elif absf(ball_position.x - NET_X) < BALL_RADIUS + 5.0 and previous_ball_position.y + BALL_RADIUS <= NET_TOP and ball_position.y + BALL_RADIUS >= NET_TOP:
		ball_position.y = NET_TOP - BALL_RADIUS
		ball_velocity.y = -absf(ball_velocity.y) * 0.58


func _award_point(winner_side: int, reason: String) -> void:
	if state not in [MatchState.PLAY, MatchState.SERVE]:
		return
	last_point_winner = winner_side
	var winner := player if winner_side < 0 else cpu
	var loser := cpu if winner_side < 0 else player
	winner.celebrate_time = 1.05
	loser.hurt_time = 0.78
	if winner_side < 0:
		player_score += 1
		performance_score += 220 + mini(rally_hits, 20) * 5
	else:
		cpu_score += 1
	current_combo = 0
	score_pulse_time = 0.45
	screen_shake = maxf(screen_shake, 3.5)
	_spawn_impact(winner.position + Vector2(0.0, -52.0), winner.color(), true)
	point_message = ("我方得分" if winner_side < 0 else "对方得分") + "  " + reason
	point_message_time = 1.2
	state = MatchState.POINT
	state_timer = 0.0
	set_complete = _is_set_complete()
	if set_complete:
		if player_score > cpu_score:
			player_sets += 1
		else:
			cpu_sets += 1
		match_complete = player_sets >= 2 or cpu_sets >= 2


func _is_set_complete() -> bool:
	return maxi(player_score, cpu_score) >= 11 and abs(player_score - cpu_score) >= 2


func _advance_after_point() -> void:
	if match_complete:
		_finish_match()
	elif set_complete:
		state = MatchState.SET_BREAK
		state_timer = 0.0
		point_message = "本局结束"
		point_message_time = 1.8
	else:
		_prepare_serve(-serve_side)


func _prepare_serve(side: int) -> void:
	serve_side = side
	state = MatchState.SERVE
	state_timer = 0.0
	last_touch_side = 0
	side_touches = 0
	serve_touch_locked_side = 0
	last_player_attack_from_backcourt = false
	ai_block_decision_hit = -1
	ai_block_decision_allowed = false
	player.position = Vector2(190, FLOOR_Y)
	cpu.position = Vector2(770, FLOOR_Y)
	player.velocity = Vector2.ZERO
	cpu.velocity = Vector2.ZERO
	player.grounded = true
	cpu.grounded = true
	_reset_actor_visual_state(player)
	_reset_actor_visual_state(cpu)
	point_message = "我方发球" if side < 0 else "对方发球"
	point_message_time = 0.8


func _reset_actor_visual_state(actor: Actor) -> void:
	actor.bump_time = 0.0
	actor.normal_hit_window = 0.0
	actor.normal_hit_mode = ""
	actor.block_time = 0.0
	actor.block_connected = false
	actor.block_reach_scale = 1.0
	actor.dive_time = 0.0
	actor.dive_has_hit = false
	actor.jump_time = 0.0
	actor.land_time = 0.0
	actor.set_time = 0.0
	actor.spike_time = 0.0
	actor.spike_ready_time = 0.0
	actor.spike_lunge_time = 0.0
	actor.spike_charge = 0.0
	actor.spike_attempted = false
	actor.spike_move_direction = 0.0
	actor.k_attack = KAttack.NONE
	actor.k_attack_direction = 0.0
	actor.shot_route = ShotRoute.STRAIGHT
	actor.fast_fall_held = false
	actor.action_lock_time = 0.0
	actor.serve_time = 0.0
	actor.hurt_time = 0.0
	actor.celebrate_time = 0.0
	actor.turn_time = 0.0
	actor.flash_time = 0.0


func _update_ai(delta: float) -> void:
	cpu.move_direction = 0.0
	cpu.fast_fall_held = false
	if state == MatchState.SERVE:
		return
	if state != MatchState.PLAY:
		return
	ai_think -= delta
	if ai_think <= 0.0:
		ai_think = _ai_reaction()
		ai_error = randf_range(-1.0, 1.0) * (55.0 * (1.0 - _ai_skill()))
		var phase := _next_touch_phase(cpu)
		ai_target_x = _ai_contact_target_x(phase) + ai_error
	if _should_ai_block():
		ai_target_x = 535.0
		if absf(cpu.position.x - ai_target_x) < 38.0 and cpu.grounded:
			_block(cpu)
	var cpu_phase := _next_touch_phase(cpu)
	if cpu_phase == TouchPhase.ATTACK and ball_position.x > NET_X:
		_update_ai_attack()
		return
	ai_target_x = clampf(ai_target_x, NET_X + 40.0, 900.0)
	cpu.move_direction = signf(ai_target_x - cpu.position.x) if absf(ai_target_x - cpu.position.x) > 12.0 else 0.0
	if ball_position.x > NET_X and ball_velocity.y > -120.0:
		var contact := _contact_geometry(cpu, cpu_phase)
		if bool(contact.valid) and cpu.hit_cooldown <= 0.0:
			var cpu_charge := 0.45 + _ai_skill() * 0.45
			match cpu_phase:
				TouchPhase.RECEIVE:
					_perform_hit(cpu, cpu_charge, "receive")
				TouchPhase.SET:
					_perform_hit(cpu, cpu_charge, "set")
		elif ball_position.y > 330.0 and absf(ball_position.x - cpu.position.x) > 55.0 and absf(ball_position.x - cpu.position.x) < 220.0 and cpu.grounded:
			_dive(cpu)


func _update_ai_attack() -> void:
	var plan := _ai_attack_plan()
	if bool(plan.valid):
		ai_target_x = clampf(float(plan.actor_x), NET_X + 40.0, 900.0)
		cpu.move_direction = signf(ai_target_x - cpu.position.x) if absf(ai_target_x - cpu.position.x) > 10.0 else 0.0
		if cpu.grounded:
			var ready_to_jump := absf(ai_target_x - cpu.position.x) <= 62.0
			if ready_to_jump and float(plan.time) <= 0.52:
				_jump(cpu)
				return
		else:
			var contact := _contact_geometry(cpu, TouchPhase.ATTACK)
			var back_court_attack := ball_position.x - NET_X > 130.0
			var minimum_quality := 0.70 if back_court_attack else 0.45
			if bool(contact.valid) and float(contact.quality) >= minimum_quality and cpu.hit_cooldown <= 0.0 and cpu.spike_ready_time <= 0.0:
				var route := ShotRoute.STRAIGHT if back_court_attack or player.position.x > NET_X - 150.0 else ShotRoute.CROSS
				_start_spike(cpu, KAttack.AIR_SPIKE, -1.0, route)
				return
			if ball_velocity.y > 180.0 and ball_position.y > cpu.position.y - 35.0:
				cpu.fast_fall_held = true
	if cpu.grounded and ball_velocity.y > 0.0 and ball_position.y > FLOOR_Y - 105.0:
		var fallback_contact := _contact_geometry(cpu, TouchPhase.ATTACK)
		if bool(fallback_contact.valid) and cpu.hit_cooldown <= 0.0:
			_perform_hit(cpu, 0.45 + _ai_skill() * 0.45, "save", float(fallback_contact.quality))


func _ai_attack_plan() -> Dictionary:
	var jump_velocity := 760.0 * float(cpu.data.jump)
	var jump_height := jump_velocity * jump_velocity / (2.0 * 1450.0)
	# Plan slightly below the theoretical apex so tiny trajectory differences never cancel the jump.
	var target_ball_y := clampf(FLOOR_Y - jump_height - 88.0 * float(cpu.data.height) + 16.0, 78.0, 155.0)
	var a := 0.5 * GRAVITY
	var b := ball_velocity.y
	var c := ball_position.y - target_ball_y
	var discriminant := b * b - 4.0 * a * c
	if discriminant < 0.0:
		return {"valid": false}
	var descending_time := (-b + sqrt(discriminant)) / (2.0 * a)
	if descending_time <= 0.0 or descending_time > 2.2:
		return {"valid": false}
	var predicted_ball_x := ball_position.x + ball_velocity.x * descending_time
	if predicted_ball_x <= NET_X + 18.0 or predicted_ball_x >= RIGHT_LIMIT:
		return {"valid": false}
	return {
		"valid": true,
		"time": descending_time,
		"actor_x": predicted_ball_x + 36.0 * float(cpu.data.reach)
	}


func _ai_contact_target_x(phase: int) -> float:
	if phase not in [TouchPhase.RECEIVE, TouchPhase.SET]:
		return _predict_landing_x()
	var ideal_offset := _ideal_contact_position(cpu, phase) - cpu.position
	var target_y := FLOOR_Y + ideal_offset.y
	var a := 0.5 * GRAVITY
	var b := ball_velocity.y
	var c := ball_position.y - target_y
	var discriminant := b * b - 4.0 * a * c
	if discriminant < 0.0:
		return _predict_landing_x()
	var descending_time := (-b + sqrt(discriminant)) / (2.0 * a)
	if descending_time <= 0.0 or descending_time > 2.5:
		return _predict_landing_x()
	var predicted_ball_x := ball_position.x + ball_velocity.x * descending_time
	return predicted_ball_x - ideal_offset.x


func _should_ai_block() -> bool:
	if cpu.block_cooldown > 0.0 or cpu.action_lock_time > 0.0 or cpu.block_fatigue >= 3:
		return false
	if last_touch_side != player.side or side_touches < 3:
		return false
	if last_player_attack_from_backcourt:
		if ai_block_decision_hit != rally_hits:
			ai_block_decision_hit = rally_hits
			ai_block_decision_allowed = randf() < BACKCOURT_BLOCK_CHANCE
		if not ai_block_decision_allowed:
			return false
	if ball_velocity.x <= 80.0 or ball_position.x >= NET_X:
		return false
	var time_to_net := (NET_X - ball_position.x) / ball_velocity.x
	if time_to_net < 0.08 or time_to_net > 0.62:
		return false
	var predicted_y := ball_position.y + ball_velocity.y * time_to_net + 0.5 * GRAVITY * time_to_net * time_to_net
	var timing_window := 0.38 + _ai_skill() * 0.12
	return time_to_net <= timing_window and predicted_y >= 85.0 and predicted_y <= NET_TOP + 52.0


func _predict_landing_x() -> float:
	var position := ball_position
	var velocity := ball_velocity
	for step in 90:
		velocity.y += GRAVITY * 0.025
		position += velocity * 0.025
		if position.y >= FLOOR_Y - BALL_RADIUS:
			break
	return position.x


func _ai_skill() -> float:
	return 0.35 if difficulty == "轻松" else (0.62 if difficulty == "普通" else 0.88)


func _ai_reaction() -> float:
	return 0.38 if difficulty == "轻松" else (0.2 if difficulty == "普通" else 0.1)


func _input(event: InputEvent) -> void:
	if state == MatchState.MATCH_OVER:
		return
	if event is InputEventKey and not event.echo:
		_handle_key(event)


func _handle_key(event: InputEventKey) -> void:
	if event.pressed and event.physical_keycode == KEY_ESCAPE:
		_toggle_pause()
		return
	if game_paused:
		return
	if runtime_mode == RuntimeMode.NETWORK_CLIENT:
		if not event.pressed:
			return
		var action := ""
		if event.physical_keycode == int(bindings.get("jump", KEY_W)):
			action = "jump"
			var local_actor := player if local_network_side < 0 else cpu
			_jump(local_actor)
		elif event.physical_keycode == int(bindings.get("hit", KEY_J)):
			action = "hit"
		elif event.physical_keycode == int(bindings.get("spike", KEY_K)):
			action = "spike"
		elif event.physical_keycode == int(bindings.get("dive", KEY_L)):
			action = "dive"
		elif event.physical_keycode == int(bindings.get("block", KEY_U)):
			action = "block"
		if not action.is_empty():
			var bridge := get_node_or_null("/root/NetworkBridge")
			if bridge:
				bridge.send_action(action)
		return
	if event.pressed:
		if event.physical_keycode == int(bindings.get("jump", KEY_W)):
			_jump(player)
		elif event.physical_keycode == int(bindings.get("hit", KEY_J)):
			_normal_hit(player)
		elif event.physical_keycode == int(bindings.get("spike", KEY_K)):
			_start_spike(player)
		elif event.physical_keycode == int(bindings.get("dive", KEY_L)):
			_dive(player)
		elif event.physical_keycode == int(bindings.get("block", KEY_U)):
			_block(player)


func _show_action(text: String, color: Color) -> void:
	action_message = text
	action_message_color = color
	action_message_time = 0.72
	action_pop_scale = 1.2 if text.begins_with("完美") or text.begins_with("精准") else 1.08
	if action_tween:
		action_tween.kill()
	if runtime_mode != RuntimeMode.NETWORK_SERVER:
		action_tween = create_tween().bind_node(self)
		action_tween.set_trans(Tween.TRANS_BACK).set_ease(Tween.EASE_OUT)
		action_tween.tween_property(self, "action_pop_scale", 1.0, 0.3)
	if text.begins_with("完美") or text.begins_with("精准"):
		timing_flash_time = 0.32


func _spawn_impact(position: Vector2, color: Color, strong: bool) -> void:
	var count := 18 if strong else 9
	for index in count:
		var angle := randf_range(-PI, PI)
		var life := randf_range(0.2, 0.45)
		particles.append({"kind": "spark", "position": position, "velocity": Vector2.from_angle(angle) * randf_range(80, 260), "life": life, "max_life": life, "gravity": 620.0, "color": color, "size": randf_range(2, 6)})
	var ring_life := 0.24 if strong else 0.16
	particles.append({"kind": "ring", "position": position, "velocity": Vector2.ZERO, "life": ring_life, "max_life": ring_life, "gravity": 0.0, "color": color, "size": 42.0 if strong else 26.0})


func _spawn_dust(position: Vector2, color: Color, count: int) -> void:
	for index in count:
		var life := randf_range(0.2, 0.42)
		particles.append({
			"kind": "dust",
			"position": position + Vector2(randf_range(-18, 18), randf_range(-4, 2)),
			"velocity": Vector2(randf_range(-75, 75), randf_range(-125, -35)),
			"life": life,
			"max_life": life,
			"gravity": 260.0,
			"color": color.lerp(COLORS.paper, 0.5),
			"size": randf_range(3, 8)
		})


func _spawn_speed_lines(position: Vector2, direction: float, color: Color, count: int) -> void:
	for index in count:
		var life := randf_range(0.1, 0.22)
		particles.append({
			"kind": "streak",
			"position": position + Vector2(randf_range(-10, 10), randf_range(-26, 26)),
			"velocity": Vector2(direction * randf_range(160, 280), randf_range(-30, 30)),
			"life": life,
			"max_life": life,
			"gravity": 0.0,
			"color": color,
			"size": randf_range(16, 34)
		})


func _finish_match() -> void:
	if state == MatchState.MATCH_OVER:
		return
	state = MatchState.MATCH_OVER
	finished.emit({"victory": player_sets > cpu_sets, "player_sets": player_sets, "cpu_sets": cpu_sets, "player_score": player_score, "cpu_score": cpu_score, "spikes": spikes, "saves": saves, "blocks": blocks, "perfect_touches": perfect_touches, "max_combo": max_combo, "performance_score": performance_score})


func make_network_snapshot() -> Dictionary:
	return {
		"state": state,
		"state_timer": state_timer,
		"serve_side": serve_side,
		"last_point_winner": last_point_winner,
		"player_score": player_score,
		"cpu_score": cpu_score,
		"player_sets": player_sets,
		"cpu_sets": cpu_sets,
		"ball_position": ball_position,
		"ball_velocity": ball_velocity,
		"ball_rotation": ball_rotation,
		"last_touch_side": last_touch_side,
		"side_touches": side_touches,
		"point_message": point_message,
		"point_message_time": point_message_time,
		"action_message": action_message,
		"action_message_color": action_message_color,
		"action_message_time": action_message_time,
		"performance_score": performance_score,
		"perfect_touches": perfect_touches,
		"current_combo": current_combo,
		"max_combo": max_combo,
		"player": _actor_network_state(player),
		"cpu": _actor_network_state(cpu)
	}


func apply_network_snapshot(snapshot: Dictionary) -> void:
	if runtime_mode != RuntimeMode.NETWORK_CLIENT or not is_instance_valid(player) or not is_instance_valid(cpu):
		return
	state = int(snapshot.get("state", state)) as MatchState
	state_timer = float(snapshot.get("state_timer", state_timer))
	serve_side = int(snapshot.get("serve_side", serve_side))
	last_point_winner = int(snapshot.get("last_point_winner", last_point_winner))
	player_score = int(snapshot.get("player_score", player_score))
	cpu_score = int(snapshot.get("cpu_score", cpu_score))
	player_sets = int(snapshot.get("player_sets", player_sets))
	cpu_sets = int(snapshot.get("cpu_sets", cpu_sets))
	var authoritative_ball_position := Vector2(snapshot.get("ball_position", ball_position))
	var authoritative_ball_velocity := Vector2(snapshot.get("ball_velocity", ball_velocity))
	var snapshot_lead := NETWORK_INPUT_INTERVAL
	network_ball_target_position = authoritative_ball_position + authoritative_ball_velocity * snapshot_lead
	if state == MatchState.PLAY:
		network_ball_target_position.y += 0.5 * GRAVITY * snapshot_lead * snapshot_lead
	network_ball_target_velocity = authoritative_ball_velocity + Vector2(0.0, GRAVITY * snapshot_lead if state == MatchState.PLAY else 0.0)
	network_ball_target_rotation = float(snapshot.get("ball_rotation", ball_rotation))
	if not network_snapshot_ready or state != MatchState.PLAY:
		ball_position = network_ball_target_position
		ball_velocity = network_ball_target_velocity
		ball_rotation = network_ball_target_rotation
		previous_ball_position = ball_position
	last_touch_side = int(snapshot.get("last_touch_side", last_touch_side))
	side_touches = int(snapshot.get("side_touches", side_touches))
	point_message = String(snapshot.get("point_message", point_message))
	point_message_time = float(snapshot.get("point_message_time", point_message_time))
	action_message = String(snapshot.get("action_message", action_message))
	action_message_color = Color(snapshot.get("action_message_color", action_message_color))
	action_message_time = float(snapshot.get("action_message_time", action_message_time))
	performance_score = int(snapshot.get("performance_score", performance_score))
	perfect_touches = int(snapshot.get("perfect_touches", perfect_touches))
	current_combo = int(snapshot.get("current_combo", current_combo))
	max_combo = int(snapshot.get("max_combo", max_combo))
	_apply_actor_network_state(player, snapshot.get("player", {}), local_network_side < 0)
	_apply_actor_network_state(cpu, snapshot.get("cpu", {}), local_network_side > 0)
	network_snapshot_ready = true
	queue_redraw()


func _actor_network_state(actor: Actor) -> Dictionary:
	return {
		"position": actor.position,
		"velocity": actor.velocity,
		"move_direction": actor.move_direction,
		"grounded": actor.grounded,
		"hit_cooldown": actor.hit_cooldown,
		"action_lock_time": actor.action_lock_time,
		"dive_time": actor.dive_time,
		"dive_direction": actor.dive_direction,
		"block_time": actor.block_time,
		"block_cooldown": actor.block_cooldown,
		"block_reach_scale": actor.block_reach_scale,
		"bump_time": actor.bump_time,
		"flash_time": actor.flash_time,
		"animation_clock": actor.animation_clock,
		"jump_time": actor.jump_time,
		"land_time": actor.land_time,
		"set_time": actor.set_time,
		"spike_time": actor.spike_time,
		"spike_ready_time": actor.spike_ready_time,
		"serve_time": actor.serve_time,
		"hurt_time": actor.hurt_time,
		"celebrate_time": actor.celebrate_time,
		"k_attack": actor.k_attack,
		"k_attack_direction": actor.k_attack_direction,
		"shot_route": actor.shot_route,
		"fast_fall_held": actor.fast_fall_held
	}


func _apply_actor_network_state(actor: Actor, data: Dictionary, locally_predicted: bool) -> void:
	if data.is_empty():
		return
	var authoritative_position := Vector2(data.get("position", actor.position))
	network_actor_target_positions[actor.side] = authoritative_position
	if locally_predicted and network_snapshot_ready and actor.position.distance_to(authoritative_position) < 90.0:
		actor.position = actor.position.lerp(authoritative_position, 0.38)
	elif locally_predicted or not network_snapshot_ready:
		actor.position = authoritative_position
	actor.velocity = Vector2(data.get("velocity", actor.velocity))
	actor.move_direction = float(data.get("move_direction", actor.move_direction))
	actor.grounded = bool(data.get("grounded", actor.grounded))
	for key in ["hit_cooldown", "action_lock_time", "dive_time", "dive_direction", "block_time", "block_cooldown", "block_reach_scale", "bump_time", "flash_time", "animation_clock", "jump_time", "land_time", "set_time", "spike_time", "spike_ready_time", "serve_time", "hurt_time", "celebrate_time", "k_attack_direction"]:
		actor.set(key, float(data.get(key, actor.get(key))))
	actor.k_attack = int(data.get("k_attack", actor.k_attack)) as KAttack
	actor.shot_route = int(data.get("shot_route", actor.shot_route)) as ShotRoute
	actor.fast_fall_held = bool(data.get("fast_fall_held", actor.fast_fall_held))


func _toggle_pause() -> void:
	game_paused = not game_paused
	if game_paused:
		action_message_time = 0.0
		point_message_time = 0.0
		queue_redraw()
		_show_pause_layer()
	elif is_instance_valid(pause_layer):
		pause_layer.queue_free()
		pause_layer = null


func _show_pause_layer() -> void:
	pause_layer = Control.new()
	pause_layer.set_anchors_and_offsets_preset(Control.PRESET_FULL_RECT)
	pause_layer.mouse_filter = Control.MOUSE_FILTER_STOP
	add_child(pause_layer)
	var shade := ColorRect.new()
	shade.color = Color(COLORS.ink, 0.48)
	shade.set_anchors_and_offsets_preset(Control.PRESET_FULL_RECT)
	pause_layer.add_child(shade)
	var panel := ColorRect.new()
	panel.color = COLORS.paper
	panel.position = Vector2(275, 92)
	panel.size = Vector2(410, 356)
	pause_layer.add_child(panel)
	var accent := ColorRect.new()
	accent.color = COLORS.cyan
	accent.position = Vector2(275, 92)
	accent.size = Vector2(410, 7)
	pause_layer.add_child(accent)
	var label := Label.new()
	label.text = "比赛暂停"
	label.position = Vector2(280, 126)
	label.size = Vector2(400, 58)
	label.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	label.add_theme_color_override("font_color", COLORS.yellow)
	label.add_theme_font_size_override("font_size", 38)
	pause_layer.add_child(label)
	var score := Label.new()
	score.text = "%02d  :  %02d" % [player_score, cpu_score]
	score.position = Vector2(280, 184)
	score.size = Vector2(400, 38)
	score.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	score.add_theme_color_override("font_color", COLORS.ink)
	score.add_theme_font_size_override("font_size", 25)
	pause_layer.add_child(score)
	var resume := Button.new()
	resume.text = "继续比赛"
	resume.position = Vector2(330, 260)
	resume.size = Vector2(300, 58)
	resume.pressed.connect(_toggle_pause)
	resume.theme_type_variation = &"PrimaryButton"
	pause_layer.add_child(resume)
	var quit := Button.new()
	quit.text = "退出比赛"
	quit.position = Vector2(330, 338)
	quit.size = Vector2(300, 58)
	quit.pressed.connect(func():
		if runtime_mode == RuntimeMode.NETWORK_CLIENT:
			var bridge := get_node_or_null("/root/NetworkBridge")
			if bridge:
				bridge.leave_online_room()
		quit_requested.emit()
	)
	quit.theme_type_variation = &"GhostButton"
	pause_layer.add_child(quit)
	pause_layer.modulate.a = 0.0
	panel.position.y += 10.0
	var entrance := create_tween().bind_node(pause_layer)
	entrance.set_parallel(true)
	entrance.set_trans(Tween.TRANS_CUBIC).set_ease(Tween.EASE_OUT)
	entrance.tween_property(pause_layer, "modulate:a", 1.0, 0.22)
	entrance.tween_property(panel, "position:y", 92.0, 0.3)


func _draw() -> void:
	var offset := Vector2(randf_range(-screen_shake, screen_shake), randf_range(-screen_shake, screen_shake)) if screen_shake > 0.2 else Vector2.ZERO
	draw_set_transform(offset)
	_draw_arena()
	_draw_hud()
	_draw_net()
	_draw_actor(player)
	_draw_actor(cpu)
	_draw_ball()
	_draw_particles()
	_draw_match_feedback()
	draw_set_transform(Vector2.ZERO)


func _draw_particles() -> void:
	for particle in particles:
		var max_life := maxf(0.01, float(particle.get("max_life", 0.4)))
		var alpha := clampf(float(particle.life) / max_life, 0.0, 1.0)
		var color := Color(particle.color, alpha)
		var kind := String(particle.get("kind", "spark"))
		var size := float(particle.size)
		if kind == "dust":
			draw_circle(particle.position, size * (1.2 - alpha * 0.35), color)
		elif kind == "streak":
			var direction: Vector2 = Vector2(particle.velocity).normalized()
			draw_line(particle.position - direction * size, particle.position, color, maxf(2.0, size * 0.13))
		elif kind == "ring":
			var progress := 1.0 - alpha
			draw_arc(particle.position, 8.0 + size * progress, 0.0, TAU, 24, color, 3.0)
		else:
			draw_rect(Rect2(particle.position - Vector2.ONE * size * 0.5, Vector2.ONE * size), color)


func _draw_arena() -> void:
	draw_rect(Rect2(0, 0, 960, 540), COLORS.paper)
	draw_rect(Rect2(0, 82, 960, 138), COLORS.wash)
	draw_rect(Rect2(0, 82, 960, 4), Color(COLORS.cyan, 0.34))
	for row in 3:
		for column in 32:
			var color: Color = [COLORS.cyan, COLORS.yellow, COLORS.red, COLORS.green][(row * 3 + column) % 4]
			var person_x := column * 31 - 8 + (row % 2) * 9
			var person_y := 106 + row * 34
			draw_circle(Vector2(person_x + 6, person_y), 5, Color(color, 0.22))
			draw_rect(Rect2(person_x, person_y + 7, 12, 14), Color(color, 0.16))
	for light_x in [82.0, 878.0]:
		draw_line(Vector2(light_x, 88), Vector2(light_x, 207), Color(COLORS.ink, 0.1), 3)
		for lamp in 4:
			draw_circle(Vector2(light_x - 18 + lamp * 12, 96), 3.5, Color(COLORS.yellow, 0.34))
	draw_rect(Rect2(32, 220, 896, 185), COLORS.court)
	draw_rect(Rect2(32, 374, 896, 31), COLORS.court_dark)
	draw_rect(Rect2(32, 220, 896, 185), Color(COLORS.ink, 0.18), false, 2)
	draw_line(Vector2(32, FLOOR_Y), Vector2(928, FLOOR_Y), COLORS.ink, 4)
	draw_line(Vector2(240, 220), Vector2(240, FLOOR_Y), Color(COLORS.cyan, 0.42), 2)
	draw_line(Vector2(720, 220), Vector2(720, FLOOR_Y), Color(COLORS.cyan, 0.42), 2)
	draw_line(Vector2(32, 286), Vector2(928, 286), Color(COLORS.ink, 0.1), 2)
	draw_rect(Rect2(0, 405, 960, 135), Color("f3f1ef"))
	for x in range(0, 960, 48):
		draw_line(Vector2(x, 405), Vector2(x + 74, 540), Color(COLORS.cyan, 0.07), 1)


func _draw_hud() -> void:
	draw_rect(Rect2(0, 0, 960, 82), Color(COLORS.panel, 0.96))
	draw_rect(Rect2(0, 78, 960, 4), Color(COLORS.cyan, 0.22))
	draw_rect(Rect2(0, 0, 8, 82), player.color())
	draw_rect(Rect2(884, 0, 8, 82), cpu.color())
	if score_pulse_time > 0.0:
		var pulse_alpha := clampf(score_pulse_time / 0.45, 0.0, 1.0) * 0.18
		if last_point_winner < 0:
			draw_rect(Rect2(15, 25, 94, 54), Color(player.color(), pulse_alpha))
		else:
			draw_rect(Rect2(787, 25, 94, 54), Color(cpu.color(), pulse_alpha))
	_draw_text(String(player.data.get("display_name", player.data.name)), Rect2(26, 8, 300, 25), 18, player.color(), HORIZONTAL_ALIGNMENT_LEFT)
	_draw_text("%02d" % player_score, Rect2(24, 31, 78, 48), 39 + (6 if score_pulse_time > 0.0 and last_point_winner < 0 else 0), COLORS.ink, HORIZONTAL_ALIGNMENT_LEFT)
	_draw_set_markers(player_sets, Vector2(111, 17), player.color(), false)
	_draw_text(String(player.data.role), Rect2(112, 46, 218, 22), 14, COLORS.muted, HORIZONTAL_ALIGNMENT_LEFT)
	if serve_side < 0:
		draw_circle(Vector2(314, 18), 4, player.color())

	_draw_text(String(cpu.data.get("display_name", cpu.data.name)), Rect2(600, 8, 272, 25), 18, cpu.color(), HORIZONTAL_ALIGNMENT_RIGHT)
	_draw_text("%02d" % cpu_score, Rect2(796, 31, 76, 48), 39 + (6 if score_pulse_time > 0.0 and last_point_winner > 0 else 0), COLORS.ink, HORIZONTAL_ALIGNMENT_RIGHT)
	_draw_set_markers(cpu_sets, Vector2(760, 17), cpu.color(), true)
	_draw_text(String(cpu.data.role), Rect2(630, 46, 152, 22), 14, COLORS.muted, HORIZONTAL_ALIGNMENT_RIGHT)
	if serve_side > 0:
		draw_circle(Vector2(608, 18), 4, cpu.color())

	draw_rect(Rect2(360, 7, 240, 68), COLORS.wash)
	draw_rect(Rect2(360, 7, 240, 68), Color(COLORS.cyan, 0.45), false, 1)
	_draw_text("第 %d 局  /  %s" % [player_sets + cpu_sets + 1, "我方发球" if serve_side < 0 else "对方发球"], Rect2(380, 11, 200, 26), 17, COLORS.yellow)
	_draw_touch_pipeline(Rect2(390, 44, 180, 22))
	var status_text := "积分 %04d" % performance_score if runtime_mode == RuntimeMode.OFFLINE else "ONLINE"
	_draw_text(status_text, Rect2(282, 50, 74, 22), 13, COLORS.muted, HORIZONTAL_ALIGNMENT_RIGHT)
	if current_combo >= 2:
		_draw_text("%d COMBO" % current_combo, Rect2(604, 50, 88, 22), 14, COLORS.yellow, HORIZONTAL_ALIGNMENT_LEFT)


func _draw_set_markers(count: int, origin: Vector2, color: Color, align_right: bool) -> void:
	_draw_text("局", Rect2(origin.x, origin.y, 22, 20), 14, COLORS.muted, HORIZONTAL_ALIGNMENT_RIGHT if align_right else HORIZONTAL_ALIGNMENT_LEFT)
	for index in 2:
		var x := origin.x + (34.0 + index * 17.0) * (-1.0 if align_right else 1.0)
		draw_rect(Rect2(x, origin.y + 7, 10, 6), color if index < count else Color(COLORS.ink, 0.12))


func _draw_touch_pipeline(rect: Rect2) -> void:
	var phase := _next_touch_phase(player) if state == MatchState.PLAY else -1
	var labels := ["垫", "传", "攻"]
	for index in 3:
		var cell := Rect2(rect.position + Vector2(index * 60.0, 0), Vector2(55, rect.size.y))
		var active := phase == index
		draw_rect(cell, COLORS.yellow if active else Color(COLORS.panel, 0.72))
		draw_rect(cell, COLORS.yellow if active else Color(COLORS.line, 0.9), false, 1)
		_draw_text(labels[index], cell, 14, Color.WHITE if active else COLORS.muted)


func _draw_match_feedback() -> void:
	if timing_flash_time > 0.0:
		var alpha := clampf(timing_flash_time / 0.32, 0.0, 1.0)
		draw_rect(Rect2(0, 82, 960, 458), Color(action_message_color, alpha * 0.055))
		draw_rect(Rect2(0, 82, 960, 4), Color(action_message_color, alpha * 0.9))
	if state == MatchState.PLAY and action_message_time > 0.0:
		var action_rect := Rect2(300, 112, 360, 56).grow((action_pop_scale - 1.0) * 32.0)
		draw_rect(Rect2(action_rect.position + Vector2(0, 5), action_rect.size), Color(COLORS.ink, 0.08))
		draw_rect(action_rect, Color(COLORS.panel, 0.94))
		draw_rect(action_rect, Color(action_message_color, 0.85), false, 2)
		_draw_text(action_message, action_rect.grow(-7), 22 + int((action_pop_scale - 1.0) * 18.0), action_message_color)
	if point_message_time > 0.0:
		var point_rect := Rect2(350, 132, 260, 48) if state == MatchState.SERVE else Rect2(292, 168, 376, 82)
		var point_color := COLORS.yellow if state == MatchState.SERVE else (player.color() if last_point_winner < 0 else cpu.color())
		draw_rect(Rect2(point_rect.position + Vector2(0, 7), point_rect.size), Color(COLORS.ink, 0.1))
		draw_rect(point_rect, Color(COLORS.panel, 0.96))
		draw_rect(point_rect, Color(point_color, 0.55), false, 1)
		draw_rect(Rect2(point_rect.position, Vector2(point_rect.size.x, 4 if state == MatchState.SERVE else 5)), point_color)
		var text_rect := point_rect.grow(-8)
		_draw_text(point_message, text_rect, 22 if state == MatchState.SERVE else 29, COLORS.ink)


func _draw_net() -> void:
	draw_line(Vector2(NET_X, NET_TOP), Vector2(NET_X, FLOOR_Y), COLORS.ink, 5)
	for y in range(int(NET_TOP + 12), int(FLOOR_Y), 15):
		draw_line(Vector2(NET_X - 17, y), Vector2(NET_X + 17, y), Color(COLORS.ink, 0.34), 2)
	draw_line(Vector2(NET_X - 20, NET_TOP), Vector2(NET_X + 20, NET_TOP), COLORS.yellow, 5)


func _draw_actor(actor: Actor) -> void:
	var pose := _actor_pose(actor)
	var facing := 1.0 if actor.side < 0 else -1.0
	draw_circle(Vector2(actor.position.x, FLOOR_Y + 3.0), 19.0, Color(0, 0, 0, 0.22))
	var has_afterimage := actor.dive_time > 0.0 or actor.spike_time > 0.0 or actor.spike_ready_time > 0.0
	if has_afterimage:
		var trail_direction := actor.dive_direction if actor.dive_time > 0.0 else facing
		if actor.spike_ready_time > 0.0 and actor.k_attack == KAttack.GROUND_SIDE and actor.k_attack_direction != 0.0:
			trail_direction = actor.k_attack_direction
		_draw_actor_parts(actor, pose, Vector2(-trail_direction * 18.0, 5.0), 0.08)
		_draw_actor_parts(actor, pose, Vector2(-trail_direction * 9.0, 2.0), 0.16)
	_draw_actor_parts(actor, pose, Vector2.ZERO, 1.0)
	if actor.fast_fall_held and not actor.grounded:
		draw_line(actor.position + Vector2(-22, -76), actor.position + Vector2(-22, -52), Color(COLORS.cyan, 0.55), 3.0)
		draw_line(actor.position + Vector2(22, -70), actor.position + Vector2(22, -46), Color(COLORS.cyan, 0.55), 3.0)


func _actor_pose(actor: Actor) -> Dictionary:
	var facing := 1.0 if actor.side < 0 else -1.0
	var pose := {
		"head": Vector2(facing * 2.0, -82.0),
		"left_shoulder": Vector2(-18.0, -63.0),
		"right_shoulder": Vector2(18.0, -63.0),
		"left_elbow": Vector2(-28.0, -51.0),
		"right_elbow": Vector2(28.0, -51.0),
		"left_hand": Vector2(-34.0, -39.0),
		"right_hand": Vector2(34.0, -39.0),
		"left_hip": Vector2(-10.0, -35.0),
		"right_hip": Vector2(10.0, -35.0),
		"left_knee": Vector2(-11.0, -18.0),
		"right_knee": Vector2(11.0, -18.0),
		"left_foot": Vector2(-13.0, 0.0),
		"right_foot": Vector2(13.0, 0.0),
		"joined_hands": false
	}
	var clock := actor.animation_clock
	if actor.celebrate_time > 0.0:
		var bounce := -absf(sin(clock * 8.0)) * 10.0
		_shift_pose(pose, Vector2(0, bounce))
		pose.left_elbow = Vector2(-24, -84 + bounce)
		pose.right_elbow = Vector2(24, -84 + bounce)
		pose.left_hand = Vector2(-30, -108 + bounce)
		pose.right_hand = Vector2(30, -108 + bounce)
		pose.left_foot.x -= 5.0
		pose.right_foot.x += 5.0
	elif actor.hurt_time > 0.0:
		var recoil := -facing * (8.0 + sin(clock * 20.0) * 2.0)
		pose.head += Vector2(recoil, 8)
		pose.left_shoulder += Vector2(recoil, 7)
		pose.right_shoulder += Vector2(recoil, 7)
		pose.left_hip.y += 9.0
		pose.right_hip.y += 9.0
		pose.left_elbow = Vector2(-facing * 18.0 - 20.0, -45.0)
		pose.right_elbow = Vector2(-facing * 18.0 + 20.0, -45.0)
		pose.left_hand = Vector2(-facing * 30.0 - 22.0, -35.0)
		pose.right_hand = Vector2(-facing * 30.0 + 22.0, -35.0)
		pose.left_knee = Vector2(-15, -12)
		pose.right_knee = Vector2(15, -12)
	elif actor.dive_time > 0.0:
		pose.head = Vector2(facing * 41.0, -34.0)
		pose.left_shoulder = Vector2(facing * 23.0, -39.0) + Vector2(0, -7)
		pose.right_shoulder = Vector2(facing * 23.0, -39.0) + Vector2(0, 7)
		pose.left_hip = Vector2(-facing * 7.0, -30.0) + Vector2(0, -6)
		pose.right_hip = Vector2(-facing * 7.0, -30.0) + Vector2(0, 6)
		pose.left_elbow = Vector2(facing * 36.0, -35.0)
		pose.right_elbow = Vector2(facing * 38.0, -27.0)
		pose.left_hand = Vector2(facing * 55.0, -34.0)
		pose.right_hand = Vector2(facing * 55.0, -26.0)
		pose.left_knee = Vector2(-facing * 26.0, -24.0)
		pose.right_knee = Vector2(-facing * 25.0, -15.0)
		pose.left_foot = Vector2(-facing * 45.0, -18.0)
		pose.right_foot = Vector2(-facing * 43.0, -8.0)
		pose.joined_hands = true
	elif actor.block_time > 0.0:
		pose.left_elbow = Vector2(-20, -86)
		pose.right_elbow = Vector2(20, -86)
		pose.left_hand = Vector2(-14, -113)
		pose.right_hand = Vector2(14, -113)
		pose.left_knee = Vector2(-14, -18)
		pose.right_knee = Vector2(14, -18)
	elif actor.spike_ready_time > 0.0 and actor.k_attack == KAttack.GROUND_UP:
		pose.left_elbow = Vector2(-19, -88)
		pose.right_elbow = Vector2(19, -88)
		pose.left_hand = Vector2(-9, -117)
		pose.right_hand = Vector2(9, -117)
		pose.left_knee = Vector2(-15, -24)
		pose.right_knee = Vector2(15, -22)
		pose.left_foot = Vector2(-18, -8)
		pose.right_foot = Vector2(18, -7)
	elif actor.spike_ready_time > 0.0 and actor.k_attack == KAttack.GROUND_SIDE:
		var direction := actor.k_attack_direction if actor.k_attack_direction != 0.0 else facing
		pose.head += Vector2(direction * 9.0, 3.0)
		pose.left_shoulder += Vector2(direction * 7.0, 2.0)
		pose.right_shoulder += Vector2(direction * 7.0, 2.0)
		pose.left_elbow = Vector2(direction * 36.0, -61.0)
		pose.right_elbow = Vector2(direction * 40.0, -54.0)
		pose.left_hand = Vector2(direction * 58.0, -58.0)
		pose.right_hand = Vector2(direction * 61.0, -50.0)
		pose.left_knee = Vector2(-direction * 16.0, -24.0)
		pose.right_knee = Vector2(direction * 18.0, -17.0)
		pose.joined_hands = true
	elif actor.spike_ready_time > 0.0 and actor.k_attack == KAttack.GROUND_DOWN:
		pose.head += Vector2(facing * 8.0, 12.0)
		pose.left_shoulder.y += 13.0
		pose.right_shoulder.y += 13.0
		pose.left_elbow = Vector2(facing * 27.0, -38.0)
		pose.right_elbow = Vector2(facing * 34.0, -34.0)
		pose.left_hand = Vector2(facing * 43.0, -23.0)
		pose.right_hand = Vector2(facing * 48.0, -19.0)
		pose.left_knee = Vector2(-16, -12)
		pose.right_knee = Vector2(16, -12)
		pose.left_foot.x -= 6.0
		pose.right_foot.x += 6.0
		pose.joined_hands = true
	elif actor.spike_time > 0.0 or actor.spike_ready_time > 0.0:
		var progress := 0.0 if actor.spike_ready_time > 0.0 else 1.0 - actor.spike_time / 0.42
		var front_hand := "right_hand" if facing > 0.0 else "left_hand"
		var front_elbow := "right_elbow" if facing > 0.0 else "left_elbow"
		var back_hand := "left_hand" if facing > 0.0 else "right_hand"
		var back_elbow := "left_elbow" if facing > 0.0 else "right_elbow"
		pose[front_hand] = Vector2(facing * lerpf(26.0, 49.0, progress), lerpf(-112.0, -48.0, progress))
		pose[front_elbow] = Vector2(facing * lerpf(23.0, 34.0, progress), lerpf(-88.0, -63.0, progress))
		pose[back_hand] = Vector2(-facing * 33.0, -54.0)
		pose[back_elbow] = Vector2(-facing * 25.0, -65.0)
		pose.left_knee = Vector2(-14, -24)
		pose.right_knee = Vector2(16, -20)
		pose.left_foot = Vector2(-19, -8)
		pose.right_foot = Vector2(20, -5)
		if actor.spike_ready_time > 0.0:
			_shift_pose(pose, Vector2(facing * 5.0, 0.0), ["head", "left_shoulder", "right_shoulder", "left_elbow", "right_elbow", "left_hand", "right_hand"])
	elif actor.set_time > 0.0:
		pose.left_elbow = Vector2(-22, -84)
		pose.right_elbow = Vector2(22, -84)
		pose.left_hand = Vector2(-13, -105)
		pose.right_hand = Vector2(13, -105)
		pose.left_hip.y += 4.0
		pose.right_hip.y += 4.0
		pose.left_knee = Vector2(-15, -15)
		pose.right_knee = Vector2(15, -15)
	elif actor.bump_time > 0.0:
		var extension := 1.0 - actor.bump_time / 0.34
		var hand_x := facing * lerpf(38.0, 52.0, minf(1.0, extension * 2.2))
		pose.left_elbow = Vector2(facing * 27.0, -48.0)
		pose.right_elbow = Vector2(facing * 31.0, -42.0)
		pose.left_hand = Vector2(hand_x, -43.0)
		pose.right_hand = Vector2(hand_x + facing * 4.0, -39.0)
		pose.left_hip.y += 8.0
		pose.right_hip.y += 8.0
		pose.left_knee = Vector2(-16, -13)
		pose.right_knee = Vector2(16, -13)
		pose.left_foot.x -= 6.0
		pose.right_foot.x += 6.0
		pose.joined_hands = true
	elif state == MatchState.SERVE and serve_side == actor.side:
		var front_hand := "right_hand" if facing > 0.0 else "left_hand"
		var front_elbow := "right_elbow" if facing > 0.0 else "left_elbow"
		var back_hand := "left_hand" if facing > 0.0 else "right_hand"
		var back_elbow := "left_elbow" if facing > 0.0 else "right_elbow"
		pose[front_hand] = Vector2(facing * 31.0, -81.0)
		pose[front_elbow] = Vector2(facing * 24.0, -65.0)
		pose[back_hand] = Vector2(-facing * 34.0, -60.0)
		pose[back_elbow] = Vector2(-facing * 24.0, -67.0)
	elif actor.serve_time > 0.0:
		var front_hand := "right_hand" if facing > 0.0 else "left_hand"
		var front_elbow := "right_elbow" if facing > 0.0 else "left_elbow"
		pose[front_hand] = Vector2(facing * 47.0, -91.0)
		pose[front_elbow] = Vector2(facing * 31.0, -75.0)
		pose.head += Vector2(facing * 4.0, -2.0)
	elif not actor.grounded:
		var rising := actor.velocity.y < 0.0
		pose.left_elbow = Vector2(-26, -73 if rising else -57)
		pose.right_elbow = Vector2(26, -73 if rising else -57)
		pose.left_hand = Vector2(-35, -88 if rising else -67)
		pose.right_hand = Vector2(35, -88 if rising else -67)
		pose.left_knee = Vector2(-15, -25)
		pose.right_knee = Vector2(15, -23)
		pose.left_foot = Vector2(-19, -9)
		pose.right_foot = Vector2(20, -7)
	elif actor.land_time > 0.0:
		var land_progress := 1.0 - actor.land_time / 0.24
		var crouch := sin(land_progress * PI) * 10.0
		_shift_pose(pose, Vector2(0, crouch * 0.45), ["head", "left_shoulder", "right_shoulder", "left_elbow", "right_elbow", "left_hand", "right_hand", "left_hip", "right_hip"])
		pose.left_knee = Vector2(-16, -11)
		pose.right_knee = Vector2(16, -11)
		pose.left_foot.x -= 5.0
		pose.right_foot.x += 5.0
	elif absf(actor.velocity.x) > 45.0:
		var stride := sin(clock * 11.0) * 13.0
		var lift_left := maxf(0.0, -sin(clock * 11.0)) * 6.0
		var lift_right := maxf(0.0, sin(clock * 11.0)) * 6.0
		var move_sign := signf(actor.velocity.x)
		pose.head.x += move_sign * 4.0
		pose.left_shoulder.x += move_sign * 3.0
		pose.right_shoulder.x += move_sign * 3.0
		pose.left_knee = Vector2(-10.0 + stride * 0.45, -17.0 - lift_left)
		pose.right_knee = Vector2(10.0 - stride * 0.45, -17.0 - lift_right)
		pose.left_foot = Vector2(-12.0 + stride, -lift_left)
		pose.right_foot = Vector2(12.0 - stride, -lift_right)
		pose.left_elbow = Vector2(-25.0 - stride * 0.5, -52.0)
		pose.right_elbow = Vector2(25.0 + stride * 0.5, -52.0)
		pose.left_hand = Vector2(-31.0 - stride * 0.75, -39.0)
		pose.right_hand = Vector2(31.0 + stride * 0.75, -39.0)
	else:
		var breath := sin(clock * 3.2) * 2.0
		_shift_pose(pose, Vector2(0, breath), ["head", "left_shoulder", "right_shoulder", "left_elbow", "right_elbow", "left_hand", "right_hand"])
		pose.left_hand.y += sin(clock * 3.2 + 0.7) * 2.0
		pose.right_hand.y += sin(clock * 3.2 + 0.7) * 2.0
	if actor.turn_time > 0.0:
		var twist := sin(actor.turn_time / 0.16 * PI) * 6.0
		pose.head.x -= actor.last_move_sign * twist
		pose.left_shoulder.x -= actor.last_move_sign * twist * 0.5
		pose.right_shoulder.x -= actor.last_move_sign * twist * 0.5
	var height := float(actor.data.height)
	var reach := float(actor.data.reach)
	for key in POSE_POINTS:
		var point := Vector2(pose[key])
		point.y *= height
		if key in ["left_shoulder", "right_shoulder", "left_elbow", "right_elbow", "left_hand", "right_hand"]:
			point.x *= reach
		pose[key] = point
	return pose


func _shift_pose(pose: Dictionary, offset: Vector2, keys: Array = POSE_POINTS) -> void:
	for key in keys:
		pose[key] = Vector2(pose[key]) + offset


func _draw_actor_parts(actor: Actor, pose: Dictionary, offset: Vector2, alpha: float) -> void:
	var base := actor.position + offset
	var facing := 1.0 if actor.side < 0 else -1.0
	var outline := Color(COLORS.ink, alpha)
	var uniform := Color(Color.WHITE if actor.flash_time > 0.0 and alpha >= 0.99 else actor.color(), alpha)
	var uniform_shadow := Color(actor.color().darkened(0.28), alpha)
	var skin := Color("f1b88c", alpha)
	var skin_shadow := Color("c77d62", alpha)
	var pants := Color("142037", alpha)

	var left_hip := base + Vector2(pose.left_hip)
	var right_hip := base + Vector2(pose.right_hip)
	var left_knee := base + Vector2(pose.left_knee)
	var right_knee := base + Vector2(pose.right_knee)
	var left_foot := base + Vector2(pose.left_foot)
	var right_foot := base + Vector2(pose.right_foot)
	_draw_limb(left_hip, left_knee, left_foot, outline, pants, 13.0, 8.0)
	_draw_limb(right_hip, right_knee, right_foot, outline, pants, 13.0, 8.0)
	draw_line(left_foot + Vector2(-5, 0), left_foot + Vector2(6, 0), outline, 7.0)
	draw_line(right_foot + Vector2(-5, 0), right_foot + Vector2(6, 0), outline, 7.0)

	var left_shoulder := base + Vector2(pose.left_shoulder)
	var right_shoulder := base + Vector2(pose.right_shoulder)
	var torso := PackedVector2Array([
		left_shoulder + Vector2(2, 1), right_shoulder + Vector2(-2, 1),
		right_hip + Vector2(5, 1), left_hip + Vector2(-5, 1)
	])
	draw_colored_polygon(torso, uniform)
	var torso_outline := PackedVector2Array([torso[0], torso[1], torso[2], torso[3], torso[0]])
	draw_polyline(torso_outline, outline, 4.0)
	draw_line(left_shoulder + Vector2(6, 6), left_hip + Vector2(2, -2), uniform_shadow, 5.0)

	var left_elbow := base + Vector2(pose.left_elbow)
	var right_elbow := base + Vector2(pose.right_elbow)
	var left_hand := base + Vector2(pose.left_hand)
	var right_hand := base + Vector2(pose.right_hand)
	_draw_limb(left_shoulder, left_elbow, left_hand, outline, skin, 11.0, 7.0)
	_draw_limb(right_shoulder, right_elbow, right_hand, outline, skin, 11.0, 7.0)
	draw_circle(left_hand, 5.0, skin_shadow)
	draw_circle(right_hand, 5.0, skin_shadow)
	if bool(pose.joined_hands):
		draw_line(left_hand - Vector2(facing * 5.0, 0), right_hand + Vector2(facing * 7.0, 0), Color(COLORS.paper, alpha), 4.0)

	var head := base + Vector2(pose.head)
	draw_rect(Rect2(head - Vector2(17, 15), Vector2(34, 30)), outline)
	draw_rect(Rect2(head - Vector2(13, 11), Vector2(26, 23)), skin)
	draw_rect(Rect2(head - Vector2(17, 16), Vector2(34, 9)), pants)
	draw_rect(Rect2(head + Vector2(-17 if facing > 0.0 else 11, -10), Vector2(6, 12)), pants)
	draw_rect(Rect2(head + Vector2(facing * 6.0 - 1.0, -2), Vector2(3, 3)), outline)
	draw_rect(Rect2(head + Vector2(facing * 13.0 - 2.0, 7), Vector2(4, 3)), skin_shadow)


func _draw_limb(start: Vector2, joint: Vector2, finish: Vector2, outline: Color, fill: Color, outline_width: float, fill_width: float) -> void:
	draw_line(start, joint, outline, outline_width)
	draw_line(joint, finish, outline, outline_width)
	draw_line(start, joint, fill, fill_width)
	draw_line(joint, finish, fill, fill_width)


func _draw_ball() -> void:
	var phase := _next_touch_phase(player)
	if state == MatchState.PLAY and phase >= 0 and (phase != TouchPhase.ATTACK or not player.grounded) and player.position.distance_to(ball_position) <= 180.0:
		var contact := _contact_geometry(player, phase)
		var quality := float(contact.quality)
		var ring_color := COLORS.green if quality >= PERFECT_TIMING_QUALITY else (COLORS.yellow if bool(contact.valid) else COLORS.muted)
		var ring_radius := lerpf(31.0, 18.0, quality)
		draw_arc(ball_position, ring_radius, 0.0, TAU, 32, Color(ring_color, 0.8), 4.0)
		if quality >= PERFECT_TIMING_QUALITY:
			for angle in [0.0, PI * 0.5, PI, PI * 1.5]:
				var direction := Vector2.from_angle(angle)
				draw_line(ball_position + direction * (ring_radius + 3.0), ball_position + direction * (ring_radius + 10.0), Color(ring_color, 0.9), 3.0)
	draw_circle(Vector2(ball_position.x, FLOOR_Y + 3), 18, Color(0, 0, 0, 0.22))
	draw_circle(ball_position + Vector2(3, 5), BALL_RADIUS, Color(0, 0, 0, 0.35))
	draw_circle(ball_position, BALL_RADIUS, Color("fff7da"))
	draw_arc(ball_position, 10, ball_rotation, ball_rotation + 2.2, 12, COLORS.cyan, 3)
	draw_arc(ball_position, 10, ball_rotation + PI, ball_rotation + PI + 2.2, 12, COLORS.red, 3)


func _draw_text(text: String, rect: Rect2, font_size: int, color: Color, alignment: HorizontalAlignment = HORIZONTAL_ALIGNMENT_CENTER) -> void:
	draw_string(get_theme_default_font(), rect.position + Vector2(0, rect.size.y * 0.72), text, alignment, rect.size.x, font_size, color)
