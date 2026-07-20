extends SceneTree

const VolleyMatch := preload("res://scripts/volley_match.gd")


func _initialize() -> void:
	_run.call_deferred()


func _run() -> void:
	var network_bridge := root.get_node("NetworkBridge")
	var scene: PackedScene = load("res://main.tscn")
	var game := scene.instantiate()
	root.add_child(game)
	await process_frame
	if game.screen_name != "选人" or game.CHARACTERS.size() != 2:
		_fail("炎哥、炎弟双角色中文选人界面未正确加载。")
		return
	var yan_ge: Dictionary = game.CHARACTERS[0]
	var yan_di: Dictionary = game.CHARACTERS[1]
	if String(yan_ge.name) != "炎哥" or String(yan_di.name) != "炎弟":
		_fail("双角色名称不正确。")
		return
	for character in game.CHARACTERS:
		for stat in ["role", "height", "speed", "jump", "power", "reach"]:
			if not character.has(stat):
				_fail("角色缺少差异化属性：" + stat)
				return
	if float(yan_ge.height) <= float(yan_di.height) or float(yan_ge.power) <= float(yan_di.power) or float(yan_ge.reach) <= float(yan_di.reach):
		_fail("炎哥没有体现高大、力量强和臂展长的定位。")
		return
	if float(yan_di.speed) <= float(yan_ge.speed) or float(yan_di.jump) <= float(yan_ge.jump):
		_fail("炎弟没有体现灵活、速度快和弹跳高的定位。")
		return
	if not game.has_method("_show_online_lobby") or not game.has_method("_create_online_room") or not game.has_method("_join_online_room"):
		_fail("主界面没有接入联机大厅、创建房间和加入房间流程。")
		return
	if game.has_method("_select_difficulty") or game.DEFAULT_DIFFICULTY != "普通":
		_fail("主菜单仍然暴露电脑难度选择。")
		return
	if game.leaderboard_labels.size() != 16:
		_fail("主菜单没有创建八行单人挑战排行榜。")
		return
	var score_fixture := {"victory": true, "player_sets": 2, "cpu_sets": 0, "spikes": 3, "saves": 2, "blocks": 1, "perfect_touches": 4, "max_combo": 3}
	if game._estimated_score(score_fixture) != 2290:
		_fail("客户端积分预估公式不正确。")
		return
	if network_bridge._normalize_room_code(" ab 12cd ") != "AB12CD" or network_bridge._sanitize_nickname("\n测试玩家\r") != "测试玩家":
		_fail("房间码或游客昵称没有正确标准化。")
		return
	game._show_key_settings()
	if game.binding_buttons.size() != 8:
		_fail("按键设置界面没有提供八项可重绑定操作。")
		return
	if int(game.DEFAULT_BINDINGS.hit) != KEY_J or int(game.DEFAULT_BINDINGS.spike) != KEY_K or int(game.DEFAULT_BINDINGS.dive) != KEY_L or int(game.DEFAULT_BINDINGS.block) != KEY_U or int(game.DEFAULT_BINDINGS.down) != KEY_S:
		_fail("默认键位没有保留J击球、K扣球、L飞扑、U拦网和S快速下落。")
		return
	var migrated_bindings: Dictionary = game._bindings_from_save({"dive": KEY_K, "block": KEY_L}, 1)
	if int(migrated_bindings.spike) != KEY_K or int(migrated_bindings.block) != KEY_U or int(migrated_bindings.dive) != KEY_L:
		_fail("旧默认键位存档没有自动迁移。")
		return
	var custom_bindings: Dictionary = game._bindings_from_save({"dive": KEY_I, "block": KEY_O}, 1)
	if int(custom_bindings.dive) != KEY_I or int(custom_bindings.block) != KEY_O:
		_fail("键位迁移错误覆盖了玩家自定义设置。")
		return
	game._show_select()

	game._start_match()
	await process_frame
	var match_scene: VolleyMatch = null
	for child in game.content.get_children():
		if child is VolleyMatch:
			match_scene = child
			break
	if not match_scene:
		_fail("未能创建1v1比赛场景。")
		return
	if String(match_scene.player.data.name) != "炎哥" or String(match_scene.cpu.data.name) != "炎弟":
		_fail("默认对局没有正确载入炎哥对炎弟。")
		return
	if float(match_scene.player.data.height) <= float(match_scene.cpu.data.height) or float(match_scene.player.data.power) <= float(match_scene.cpu.data.power):
		_fail("角色差异在比赛初始化时被错误归一化。")
		return
	var tall_pose: Dictionary = match_scene._actor_pose(match_scene.player)
	var short_pose: Dictionary = match_scene._actor_pose(match_scene.cpu)
	if Vector2(tall_pose.head).y >= Vector2(short_pose.head).y or match_scene._block_contact_position(match_scene.player).y >= match_scene._block_contact_position(match_scene.cpu).y:
		_fail("身高没有同步影响人物模型和拦网触点。")
		return
	match_scene.ball_position = Vector2(VolleyMatch.NET_X, 110.0)
	match_scene.player.shot_route = VolleyMatch.ShotRoute.STRAIGHT
	match_scene.cpu.shot_route = VolleyMatch.ShotRoute.STRAIGHT
	var yan_ge_spike_speed := absf(match_scene._air_spike_velocity(match_scene.player, 1.0).x)
	var yan_di_spike_speed := absf(match_scene._air_spike_velocity(match_scene.cpu, 1.0).x)
	if yan_ge_spike_speed <= yan_di_spike_speed * 1.25:
		_fail("炎哥和炎弟的重扣速度没有形成清晰差异。")
		return
	match_scene.set_process(false)
	match_scene._prepare_serve(-1)
	match_scene._update_player_input()
	match_scene._jump(match_scene.player)
	if match_scene.player.move_direction != 0.0 or not match_scene.player.grounded:
		_fail("发球等待阶段仍然可以移动或跳跃。")
		return
	if match_scene.has_method("_control_at") or match_scene.has_method("_handle_touch") or match_scene.has_method("_draw_controls"):
		_fail("桌面Web版本仍然保留手机触屏控制逻辑。")
		return
	match_scene._prepare_serve(1)
	match_scene._normal_hit(match_scene.player)
	if match_scene.state != VolleyMatch.MatchState.SERVE or match_scene.serve_side != 1:
		_fail("电脑发球时玩家仍能提前击球。")
		return
	match_scene.state = VolleyMatch.MatchState.PLAY
	var dust_before_jump := match_scene.particles.size()
	match_scene._jump(match_scene.player)
	if match_scene.player.velocity.y > -710.0 or match_scene.player.jump_time <= 0.0 or match_scene.particles.size() <= dust_before_jump:
		_fail("角色弹跳力没有提高到球网以上。")
		return
	match_scene.player.position.y = VolleyMatch.FLOOR_Y
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = true
	seed(20260717)
	match_scene._prepare_serve(-1)
	match_scene.ball_position = match_scene.player.position + Vector2(30.0, -82.0)
	match_scene._serve_ball(match_scene.player, 0.5)
	if match_scene.player.serve_time <= 0.0:
		_fail("发球没有触发角色挥臂动画。")
		return
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.RECEIVE)
	if match_scene._perform_hit(match_scene.player, 0.5) or match_scene.side_touches != 1:
		_fail("发球后同一方仍能在对手触球前连续触球。")
		return
	var full_ai_return_crossed := false
	for step in 900:
		var dt := 1.0 / 120.0
		match_scene._update_ai(dt)
		match_scene._update_actor(match_scene.player, dt)
		match_scene._update_actor(match_scene.cpu, dt)
		if match_scene.state == VolleyMatch.MatchState.PLAY:
			match_scene._update_ball(dt)
		if match_scene.previous_ball_position.x > VolleyMatch.NET_X and match_scene.ball_position.x < VolleyMatch.NET_X and match_scene.ball_velocity.x < 0.0:
			full_ai_return_crossed = true
			break
		if match_scene.state != VolleyMatch.MatchState.PLAY:
			break
	if not full_ai_return_crossed:
		_fail("电脑没有在完整发球回合中移动接球并把球打回我方：state=%s ball=%s velocity=%s cpu=%s touches=%s last=%s" % [match_scene.state, match_scene.ball_position, match_scene.ball_velocity, match_scene.cpu.position, match_scene.side_touches, match_scene.last_touch_side])
		return
	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene.cpu.position = Vector2(535.0, VolleyMatch.FLOOR_Y)
	match_scene.cpu.velocity = Vector2.ZERO
	match_scene.cpu.grounded = true
	match_scene.cpu.block_time = 0.0
	match_scene.cpu.block_cooldown = 0.0
	match_scene.cpu.block_fatigue = 0
	match_scene.cpu.action_lock_time = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 3
	match_scene.ball_position = Vector2(350.0, 125.0)
	match_scene.ball_velocity = Vector2(420.0, -20.0)
	if not match_scene._should_ai_block():
		_fail("电脑没有识别即将经过网口的可拦球路。")
		return
	match_scene._update_ai(0.01)
	if match_scene.cpu.block_time <= 0.0:
		_fail("电脑预测到球路后没有发动拦网。")
		return
	if match_scene._should_ai_block():
		_fail("电脑在拦网冷却期间仍试图连续拦网。")
		return
	match_scene.cpu.block_time = 0.0
	match_scene.cpu.block_cooldown = 0.0
	match_scene.cpu.block_fatigue = 0
	match_scene.ball_velocity = Vector2(420.0, 720.0)
	if match_scene._should_ai_block():
		_fail("电脑对明显落不到网口高度的球仍然起跳拦网。")
		return

	match_scene.cpu.block_time = 0.0
	match_scene.cpu.block_cooldown = 0.0
	match_scene.cpu.block_fatigue = 0
	match_scene.cpu.action_lock_time = 0.0
	match_scene.player.position.x = VolleyMatch.NET_X - VolleyMatch.BACKCOURT_SPIKE_DISTANCE - 1.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 3
	match_scene.last_player_attack_from_backcourt = true
	match_scene.ball_position = Vector2(350.0, 125.0)
	match_scene.ball_velocity = Vector2(420.0, -20.0)
	seed(20260719)
	var backcourt_block_count := 0
	for decision in 400:
		match_scene.rally_hits = 1000 + decision
		match_scene.ai_block_decision_hit = -1
		if match_scene._should_ai_block():
			backcourt_block_count += 1
	if backcourt_block_count < 40 or backcourt_block_count > 80:
		_fail("电脑面对后场扣球的拦网率没有稳定在约15%%：%d/400" % backcourt_block_count)
		return
	match_scene.rally_hits = 2000
	match_scene.ai_block_decision_hit = -1
	var first_block_decision := match_scene._should_ai_block()
	for repeated_check in 60:
		if match_scene._should_ai_block() != first_block_decision:
			_fail("电脑对同一次后场来球重复抽取了拦网决策。")
			return
	match_scene.ai_block_decision_hit = match_scene.rally_hits
	match_scene.ai_block_decision_allowed = false
	match_scene.ai_think = 0.0
	match_scene.cpu.position = Vector2(770.0, VolleyMatch.FLOOR_Y)
	match_scene.cpu.velocity = Vector2.ZERO
	match_scene.cpu.grounded = true
	match_scene._update_ai(0.01)
	if match_scene.cpu.block_time > 0.0 or is_equal_approx(match_scene.ai_target_x, 535.0):
		_fail("电脑放弃后场拦网后没有切换到接球站位。")
		return
	match_scene.last_player_attack_from_backcourt = false
	match_scene.ai_block_decision_hit = -1
	if not match_scene._should_ai_block():
		_fail("降低后场拦网率时错误影响了前场拦网逻辑。")
		return

	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene.cpu.position = Vector2(770.0, VolleyMatch.FLOOR_Y)
	match_scene.cpu.velocity = Vector2.ZERO
	match_scene.cpu.grounded = true
	match_scene.cpu.hit_cooldown = 0.0
	match_scene.cpu.action_lock_time = 0.0
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.cpu, VolleyMatch.TouchPhase.RECEIVE)
	match_scene.last_touch_side = -1
	match_scene.side_touches = 1
	if not match_scene._perform_hit(match_scene.cpu, 0.7, "receive") or match_scene.ball_velocity.y > -610.0 or match_scene.cpu.bump_time <= 0.0:
		_fail("电脑没有完成第一触球垫高。")
		return
	match_scene.cpu.hit_cooldown = 0.0
	match_scene.cpu.action_lock_time = 0.0
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.cpu, VolleyMatch.TouchPhase.SET)
	if not match_scene._perform_hit(match_scene.cpu, 0.8, "set") or match_scene.ball_velocity.x >= 0.0 or match_scene.ball_velocity.y > -640.0 or match_scene.cpu.set_time <= 0.0:
		_fail("电脑没有完成第二触球二传。")
		return
	match_scene.cpu.position = Vector2(680.0, 205.0)
	match_scene.cpu.velocity = Vector2.ZERO
	match_scene.cpu.grounded = false
	match_scene.cpu.hit_cooldown = 0.0
	match_scene.cpu.action_lock_time = 0.0
	match_scene.ball_position = Vector2(350.0, 120.0)
	match_scene._start_spike(match_scene.cpu, VolleyMatch.KAttack.AIR_SPIKE, -1.0)
	match_scene.ball_position = match_scene._k_attack_contact_position(match_scene.cpu)
	match_scene._update_prepared_spike(match_scene.cpu)
	if match_scene.side_touches != 3 or match_scene.cpu.spike_time <= 0.0 or match_scene.ball_velocity.x >= 0.0:
		_fail("电脑没有完成第三触球起跳扣球。")
		return
	var ai_attack_crossed_net := false
	for step in 120:
		match_scene._update_ball(0.01)
		if match_scene.ball_position.x < VolleyMatch.NET_X and match_scene.ball_velocity.x < 0.0:
			ai_attack_crossed_net = true
			break
	if not ai_attack_crossed_net or match_scene.ball_position.y + VolleyMatch.BALL_RADIUS >= VolleyMatch.NET_TOP:
		_fail("电脑进攻轨迹没有从球网上方进入我方场地。")
		return

	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene.serve_touch_locked_side = 0
	match_scene.cpu.position = Vector2(770.0, VolleyMatch.FLOOR_Y)
	match_scene.cpu.velocity = Vector2.ZERO
	match_scene.cpu.grounded = true
	match_scene.cpu.hit_cooldown = 0.0
	match_scene.cpu.action_lock_time = 0.0
	match_scene.cpu.spike_ready_time = 0.0
	match_scene.cpu.spike_time = 0.0
	match_scene.player.position = Vector2(320.0, VolleyMatch.FLOOR_Y)
	match_scene.last_touch_side = 1
	match_scene.side_touches = 1
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.cpu, VolleyMatch.TouchPhase.SET)
	match_scene.ball_velocity = Vector2.ZERO
	if not match_scene._perform_hit(match_scene.cpu, 0.8, "set", 1.0):
		_fail("电脑预测扣球测试没有完成二传。")
		return
	var predictive_jump_seen := false
	var predictive_spike_seen := false
	var predictive_spike_crossed := false
	var predictive_contact_position := Vector2.ZERO
	var predictive_contact_velocity := Vector2.ZERO
	for step in 420:
		var dt := 1.0 / 120.0
		match_scene._update_ai(dt)
		match_scene._update_actor(match_scene.player, dt)
		match_scene._update_actor(match_scene.cpu, dt)
		if not match_scene.cpu.grounded:
			predictive_jump_seen = true
		if match_scene.cpu.spike_time > 0.0 and not predictive_spike_seen:
			predictive_spike_seen = true
			predictive_contact_position = match_scene.ball_position
			predictive_contact_velocity = match_scene.ball_velocity
		if match_scene.state == VolleyMatch.MatchState.PLAY:
			match_scene._update_ball(dt)
		if match_scene.previous_ball_position.x > VolleyMatch.NET_X and match_scene.ball_position.x < VolleyMatch.NET_X and match_scene.ball_velocity.x < 0.0:
			predictive_spike_crossed = true
			break
		if match_scene.state != VolleyMatch.MatchState.PLAY:
			break
	if not predictive_jump_seen or not predictive_spike_seen or not predictive_spike_crossed:
		_fail("电脑预测扣球失败：jump=%s spike=%s crossed=%s contact=%s contact_velocity=%s ball=%s cpu=%s velocity=%s" % [predictive_jump_seen, predictive_spike_seen, predictive_spike_crossed, predictive_contact_position, predictive_contact_velocity, match_scene.ball_position, match_scene.cpu.position, match_scene.ball_velocity])
		return

	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene.serve_touch_locked_side = 0
	match_scene.player.position = Vector2(330.0, VolleyMatch.FLOOR_Y)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = true
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.spike_time = 0.0
	match_scene.player.spike_ready_time = 0.0
	match_scene.last_touch_side = 1
	match_scene.side_touches = 1
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.RECEIVE)
	match_scene.ball_velocity = Vector2.ZERO
	match_scene._normal_hit(match_scene.player)
	if match_scene.player.bump_time <= 0.0 or match_scene.player.spike_time > 0.0 or match_scene.action_message != "完美垫球" or absf(match_scene.ball_velocity.x) < 1.0 or match_scene.ball_position.x >= VolleyMatch.NET_X:
		_fail("玩家第一触球没有执行垫球起高。")
		return

	match_scene.player.position = Vector2(260.0, VolleyMatch.FLOOR_Y)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = true
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.bump_time = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 1
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.SET)
	match_scene.ball_velocity = Vector2.ZERO
	match_scene._normal_hit(match_scene.player)
	if match_scene.last_touch_side != -1 or match_scene.side_touches != 2 or match_scene.ball_velocity.x <= 0.0:
		_fail("玩家第二触球没有完成二传。")
		return
	if match_scene.ball_velocity.y > -640.0 or match_scene.player.set_time <= 0.0 or match_scene.action_message != "精准二传":
		_fail("玩家二传高度不足、提示错误或没有进入二传动作。")
		return

	match_scene.player.position = Vector2(320.0, 280.0)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = false
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.bump_time = 0.0
	match_scene.player.set_time = 0.0
	match_scene.last_touch_side = 1
	match_scene.side_touches = 1
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.RECEIVE)
	match_scene._normal_hit(match_scene.player)
	if match_scene.player.bump_time > 0.0 or match_scene.player.normal_hit_window > 0.0 or match_scene.last_touch_side != 1 or match_scene.side_touches != 1 or match_scene.action_message != "落地后垫球":
		_fail("第一触球仍然允许玩家在空中垫球。")
		return
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 1
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.SET)
	match_scene._normal_hit(match_scene.player)
	if match_scene.player.set_time > 0.0 or match_scene.player.normal_hit_window > 0.0 or match_scene.side_touches != 1 or match_scene.action_message != "落地后二传":
		_fail("第二触球仍然允许玩家在空中二传。")
		return

	match_scene.cpu.position = Vector2(700.0, 280.0)
	match_scene.cpu.velocity = Vector2.ZERO
	match_scene.cpu.grounded = false
	match_scene.cpu.hit_cooldown = 0.0
	match_scene.cpu.action_lock_time = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 0
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.cpu, VolleyMatch.TouchPhase.RECEIVE)
	if match_scene._perform_hit(match_scene.cpu, 0.8, "receive") or match_scene.last_touch_side != -1 or match_scene.side_touches != 0:
		_fail("电脑仍然可以在空中垫球。")
		return

	match_scene.player.position = Vector2(320.0, 280.0)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = false
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.spike_ready_time = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 2
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.ATTACK)
	match_scene._normal_hit(match_scene.player)
	if match_scene.side_touches != 3 or match_scene.player.shot_route != VolleyMatch.ShotRoute.TIP or match_scene.action_message != "完美吊球":
		_fail("第三触球在空中按J时没有执行吊球。")
		return
	if match_scene.perfect_touches < 1 or match_scene.max_combo < 1 or match_scene.performance_score <= 0:
		_fail("完美触球没有进入连击和表现积分。")
		return

	match_scene.player.position = Vector2(340.0, 280.0)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = false
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.move_direction = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 2
	match_scene.ball_position = Vector2(700.0, 120.0)
	match_scene._start_spike(match_scene.player)
	if match_scene.player.k_attack != VolleyMatch.KAttack.AIR_SPIKE:
		_fail("第三触球没有进入空中扣球准备阶段。")
		return
	match_scene.ball_position = match_scene._k_attack_contact_position(match_scene.player)
	match_scene._update_prepared_spike(match_scene.player)
	if match_scene.side_touches != 3 or match_scene.player.spike_time <= 0.0 or match_scene.action_message != "完美直线重扣" or match_scene.ball_velocity.y <= 0.0:
		_fail("第三触球没有在正确时机完成扣球。")
		return

	match_scene.player.position = Vector2(260.0, VolleyMatch.FLOOR_Y)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = true
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.spike_time = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 2
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.ATTACK)
	match_scene._normal_hit(match_scene.player)
	if match_scene.side_touches != 3 or match_scene.ball_velocity.x <= 0.0 or match_scene.action_message != "精准保命":
		_fail("第三触球没有允许用J完成保命回球。")
		return

	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.set_time = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 1
	match_scene.ball_position = match_scene.player.hand_position()
	if not match_scene._perform_hit(match_scene.player, 0.35, "set", 1.0):
		_fail("玩家没有成功完成自抛二传。")
		return
	var precise_set_velocity := match_scene.ball_velocity
	var set_start_x := match_scene.ball_position.x
	var precise_set_apex_x := set_start_x + precise_set_velocity.x * (-precise_set_velocity.y / VolleyMatch.GRAVITY)
	if match_scene.side_touches != 2 or precise_set_velocity.y > -650.0 or match_scene.action_message != "精准二传":
		_fail("精准自抛二传没有消耗一次触球，或轨迹没有留在本方并升到进攻高度。")
		return
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.last_touch_side = 1
	match_scene.side_touches = 1
	match_scene.ball_position = match_scene.player.hand_position()
	if not match_scene._perform_hit(match_scene.player, 0.35, "set", 0.0):
		_fail("边缘 timing 的自抛二传没有触发。")
		return
	var poor_set_velocity := match_scene.ball_velocity
	var poor_set_apex_x := match_scene.ball_position.x + poor_set_velocity.x * (-poor_set_velocity.y / VolleyMatch.GRAVITY)
	if poor_set_velocity.y <= precise_set_velocity.y or absf(poor_set_apex_x - match_scene.player.position.x) <= absf(precise_set_apex_x - match_scene.player.position.x) + 75.0 or match_scene.action_message != "勉强二传":
		_fail("接触 timing 没有影响自抛二传的高度和落点质量。")
		return
	match_scene.ball_position = match_scene.player.position + Vector2(-20.0, -92.0)
	var backward_set_velocity := match_scene._self_set_velocity(match_scene.player, 0.0)
	var backward_set_apex_x := match_scene.ball_position.x + backward_set_velocity.x * (-backward_set_velocity.y / VolleyMatch.GRAVITY)
	if backward_set_apex_x >= match_scene.player.position.x:
		_fail("球在人物后侧时，差timing二传没有向后偏离。")
		return

	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.RECEIVE)
	var receive_start := match_scene.ball_position
	var precise_receive_velocity := match_scene._receive_velocity(match_scene.player, 1.0)
	var poor_receive_velocity := match_scene._receive_velocity(match_scene.player, 0.0)
	var precise_receive_apex_x := receive_start.x + precise_receive_velocity.x * (-precise_receive_velocity.y / VolleyMatch.GRAVITY)
	var poor_receive_apex_x := receive_start.x + poor_receive_velocity.x * (-poor_receive_velocity.y / VolleyMatch.GRAVITY)
	var precise_receive_apex_y := receive_start.y - precise_receive_velocity.y * precise_receive_velocity.y / (2.0 * VolleyMatch.GRAVITY)
	var poor_receive_apex_y := receive_start.y - poor_receive_velocity.y * poor_receive_velocity.y / (2.0 * VolleyMatch.GRAVITY)
	if precise_receive_apex_y >= poor_receive_apex_y - 55.0 or absf(poor_receive_apex_x - match_scene.player.position.x) <= absf(precise_receive_apex_x - match_scene.player.position.x) + 65.0:
		_fail("一传timing没有形成高近与低远的轨迹差异。")
		return

	match_scene.player.position = Vector2(260.0, VolleyMatch.FLOOR_Y)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = true
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.bump_time = 0.0
	match_scene.last_touch_side = 1
	match_scene.side_touches = 1
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.RECEIVE) + Vector2(100.0, 0.0)
	match_scene.ball_velocity = Vector2.ZERO
	match_scene._normal_hit(match_scene.player)
	if match_scene.player.normal_hit_window <= 0.0 or match_scene.player.bump_time <= 0.0 or match_scene.last_touch_side != 1:
		_fail("玩家提前按J时没有进入可见的垫球有效窗口。")
		return
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.RECEIVE) + Vector2(70.0, 0.0)
	match_scene._update_normal_hit_window(match_scene.player, 0.05)
	if match_scene.player.normal_hit_window > 0.0 or match_scene.last_touch_side != -1 or match_scene.side_touches != 1 or match_scene.ball_velocity.y > -425.0:
		_fail("球在垫球有效窗口内进入范围后没有被成功垫起。")
		return
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.last_touch_side = 1
	match_scene.side_touches = 1
	match_scene.ball_position = match_scene.player.position + Vector2(-35.0, -46.0)
	match_scene._normal_hit(match_scene.player)
	match_scene._update_normal_hit_window(match_scene.player, VolleyMatch.NORMAL_HIT_WINDOW + 0.01)
	if match_scene.last_touch_side != 1 or match_scene.side_touches != 1:
		_fail("球位于人物身后时仍被错误判定为有效垫球。")
		return
	match_scene.cpu.position = Vector2(720.0, VolleyMatch.FLOOR_Y)
	match_scene.cpu.velocity = Vector2.ZERO
	match_scene.cpu.grounded = true
	match_scene.cpu.hit_cooldown = 0.0
	match_scene.cpu.action_lock_time = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 1
	var right_receive_point := match_scene._ideal_contact_position(match_scene.cpu, VolleyMatch.TouchPhase.RECEIVE)
	match_scene.previous_ball_position = right_receive_point + Vector2(-52.0, 0.0)
	match_scene.ball_position = right_receive_point + Vector2(52.0, 0.0)
	match_scene.ball_velocity = Vector2(820.0, 120.0)
	match_scene._normal_hit(match_scene.cpu)
	if match_scene.last_touch_side != 1 or match_scene.side_touches != 1 or match_scene.cpu.bump_time <= 0.0 or match_scene.ball_velocity.y >= -400.0:
		_fail("右侧人物漏判了单帧穿过垫球接触区的高速球。")
		return

	if Rect2(Vector2(VolleyMatch.CPU_HUD_RIGHT - 230.0, 10.0), Vector2(230.0, 69.0)).intersects(VolleyMatch.PAUSE_BUTTON_RECT):
		_fail("电脑比分区域仍然和暂停按钮重叠。")
		return

	match_scene.player.position = Vector2(320.0, VolleyMatch.FLOOR_Y)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = true
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.move_direction = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 2
	match_scene.ball_position = Vector2(700.0, 120.0)
	match_scene._start_spike(match_scene.player)
	if match_scene.player.spike_ready_time > 0.0 or not match_scene.player.grounded or match_scene.player.velocity != Vector2.ZERO:
		_fail("地面K仍然触发了扣球动作。")
		return

	match_scene.player.position = Vector2(320.0, 280.0)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = false
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.move_direction = 1.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 2
	match_scene.ball_position = Vector2(700.0, 120.0)
	match_scene._start_spike(match_scene.player)
	if match_scene.player.k_attack != VolleyMatch.KAttack.AIR_SPIKE or match_scene.player.shot_route != VolleyMatch.ShotRoute.CROSS:
		_fail("方向加K没有准备斜线扣球。")
		return
	match_scene.ball_position = match_scene._k_attack_contact_position(match_scene.player)
	match_scene._update_prepared_spike(match_scene.player)
	var cross_landing := match_scene._predict_landing_x()
	if match_scene.action_message != "完美斜线扣球":
		_fail("斜线扣球没有显示正确时机提示。")
		return

	match_scene.player.spike_ready_time = 0.0
	match_scene.player.spike_attempted = false
	match_scene.player.k_attack = VolleyMatch.KAttack.NONE
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.move_direction = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 2
	match_scene.ball_position = Vector2(700.0, 120.0)
	match_scene._start_spike(match_scene.player)
	if match_scene.player.k_attack != VolleyMatch.KAttack.AIR_SPIKE or match_scene.player.shot_route != VolleyMatch.ShotRoute.STRAIGHT:
		_fail("空中原地K没有准备直线重扣。")
		return
	match_scene.ball_position = match_scene._k_attack_contact_position(match_scene.player)
	match_scene._update_prepared_spike(match_scene.player)
	var straight_landing := match_scene._predict_landing_x()
	var perfect_spike_speed := absf(match_scene.ball_velocity.x)

	match_scene.player.spike_ready_time = 0.0
	match_scene.player.spike_attempted = false
	match_scene.player.k_attack = VolleyMatch.KAttack.NONE
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.move_direction = 0.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 2
	match_scene.ball_position = Vector2(700.0, 120.0)
	match_scene._start_spike(match_scene.player)
	if match_scene.player.k_attack != VolleyMatch.KAttack.AIR_SPIKE or match_scene.player.shot_route != VolleyMatch.ShotRoute.STRAIGHT:
		_fail("空中K没有保持为直线扣球。")
		return
	match_scene.ball_position = match_scene._k_attack_contact_position(match_scene.player)
	if not match_scene._perform_k_attack(match_scene.player, 0.1) or match_scene.player.shot_route != VolleyMatch.ShotRoute.STRAIGHT or match_scene.ball_velocity.y >= 0.0:
		_fail("后场较差timing的K没有保持包球弧线。")
		return
	if perfect_spike_speed <= absf(match_scene.ball_velocity.x) + 120.0:
		_fail("扣球timing没有显著影响球速。")
		return
	if not (cross_landing < straight_landing):
		_fail("斜线扣球和直线重扣的落点没有形成层次。")
		return

	match_scene.player.position = Vector2(VolleyMatch.NET_X - VolleyMatch.BACKCOURT_SPIKE_DISTANCE - 1.0, 275.0)
	match_scene.player.shot_route = VolleyMatch.ShotRoute.STRAIGHT
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.ATTACK)
	var player_backcourt_velocity := match_scene._air_spike_velocity(match_scene.player, 1.0)
	var player_net_time := (VolleyMatch.NET_X - match_scene.ball_position.x) / player_backcourt_velocity.x
	var player_net_y := match_scene.ball_position.y + player_backcourt_velocity.y * player_net_time + 0.5 * VolleyMatch.GRAVITY * player_net_time * player_net_time
	var player_landing_time := (-player_backcourt_velocity.y + sqrt(player_backcourt_velocity.y * player_backcourt_velocity.y + 2.0 * VolleyMatch.GRAVITY * (VolleyMatch.FLOOR_Y - VolleyMatch.BALL_RADIUS - match_scene.ball_position.y))) / VolleyMatch.GRAVITY
	var player_landing_x := match_scene.ball_position.x + player_backcourt_velocity.x * player_landing_time
	if player_backcourt_velocity.y >= 0.0 or player_net_y > VolleyMatch.NET_TOP - VolleyMatch.BALL_RADIUS - 10.0 or player_landing_x <= VolleyMatch.NET_X or player_landing_x >= VolleyMatch.RIGHT_LIMIT:
		_fail("玩家后场包球没有形成上扬弧线或安全越网。")
		return
	match_scene.player.position.x = VolleyMatch.NET_X - VolleyMatch.BACKCOURT_SPIKE_DISTANCE
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.ATTACK)
	if match_scene._air_spike_velocity(match_scene.player, 1.0).y <= 0.0:
		_fail("150像素边界没有保留前场向下重扣。")
		return
	match_scene.cpu.position = Vector2(VolleyMatch.NET_X + VolleyMatch.BACKCOURT_SPIKE_DISTANCE + 1.0, 275.0)
	match_scene.cpu.shot_route = VolleyMatch.ShotRoute.STRAIGHT
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.cpu, VolleyMatch.TouchPhase.ATTACK)
	var cpu_backcourt_velocity := match_scene._air_spike_velocity(match_scene.cpu, 1.0)
	var cpu_net_time := (VolleyMatch.NET_X - match_scene.ball_position.x) / cpu_backcourt_velocity.x
	var cpu_net_y := match_scene.ball_position.y + cpu_backcourt_velocity.y * cpu_net_time + 0.5 * VolleyMatch.GRAVITY * cpu_net_time * cpu_net_time
	var cpu_landing_time := (-cpu_backcourt_velocity.y + sqrt(cpu_backcourt_velocity.y * cpu_backcourt_velocity.y + 2.0 * VolleyMatch.GRAVITY * (VolleyMatch.FLOOR_Y - VolleyMatch.BALL_RADIUS - match_scene.ball_position.y))) / VolleyMatch.GRAVITY
	var cpu_landing_x := match_scene.ball_position.x + cpu_backcourt_velocity.x * cpu_landing_time
	if cpu_backcourt_velocity.y >= 0.0 or cpu_net_y > VolleyMatch.NET_TOP - VolleyMatch.BALL_RADIUS - 10.0 or cpu_landing_x >= VolleyMatch.NET_X or cpu_landing_x <= VolleyMatch.LEFT_LIMIT:
		_fail("电脑后场包球没有使用和玩家一致的安全弧线。")
		return

	match_scene.player.spike_ready_time = 0.0
	match_scene.player.spike_attempted = false
	match_scene.player.position = Vector2(320.0, 300.0)
	match_scene.player.velocity = Vector2(0.0, 35.0)
	match_scene.player.grounded = false
	match_scene.player.dive_time = 0.0
	var air_l_velocity := match_scene.player.velocity
	match_scene._dive(match_scene.player)
	if match_scene.player.dive_time > 0.0 or match_scene.player.velocity != air_l_velocity:
		_fail("空中按L仍然触发了动作或改变了速度。")
		return
	match_scene.player.fast_fall_held = true
	match_scene._update_actor(match_scene.player, 0.1)
	if match_scene.player.velocity.y < 220.0:
		_fail("空中按下方向没有触发快速下落。")
		return
	match_scene.player.fast_fall_held = false
	match_scene.player.position.y = VolleyMatch.FLOOR_Y
	match_scene._update_actor(match_scene.player, 0.01)
	match_scene.player.position = Vector2(320.0, 280.0)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = false
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.spike_ready_time = 0.0
	match_scene.player.move_direction = 1.0
	match_scene.last_touch_side = -1
	match_scene.side_touches = 2
	match_scene.ball_position = Vector2(800.0, 100.0)
	match_scene._start_spike(match_scene.player)
	match_scene._update_actor(match_scene.player, VolleyMatch.K_ATTACK_WINDOW + 0.01)
	if match_scene.player.hit_cooldown < 0.4 or match_scene.player.action_lock_time < 0.2:
		_fail("K招式挥空后没有进入可惩罚硬直。")
		return

	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene.serve_touch_locked_side = 0
	match_scene.player.position = Vector2(260.0, VolleyMatch.FLOOR_Y)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = true
	match_scene.player.dive_time = 0.0
	match_scene.player.dive_has_hit = false
	match_scene.player.move_direction = 1.0
	match_scene._dive(match_scene.player)
	var forward_dive_contact: Vector2 = match_scene._dive_contact_position(match_scene.player)
	if forward_dive_contact.x <= match_scene.player.position.x:
		_fail("向前飞扑的救球判定点方向错误。")
		return
	match_scene.last_touch_side = 1
	match_scene.side_touches = 1
	match_scene.ball_position = forward_dive_contact
	match_scene.ball_velocity = Vector2(0.0, 280.0)
	var saves_before_dive := match_scene.saves
	match_scene._check_dive_contact(match_scene.player)
	if not match_scene.player.dive_has_hit or match_scene.ball_velocity.y > -600.0 or match_scene.ball_velocity.x <= 0.0 or match_scene.saves != saves_before_dive + 1:
		_fail("飞扑第一时间没有把球高高救起。")
		return
	var rally_hits_after_dive := match_scene.rally_hits
	match_scene.ball_position = match_scene._dive_contact_position(match_scene.player)
	match_scene._check_dive_contact(match_scene.player)
	if match_scene.rally_hits != rally_hits_after_dive or match_scene.saves != saves_before_dive + 1:
		_fail("同一次飞扑重复触发了救球判定。")
		return
	match_scene.player.grounded = true
	match_scene.player.dive_time = 0.0
	match_scene.player.dive_has_hit = false
	match_scene.player.move_direction = -1.0
	match_scene._dive(match_scene.player)
	if match_scene._dive_contact_position(match_scene.player).x >= match_scene.player.position.x:
		_fail("向后飞扑的救球判定点没有跟随方向。")
		return
	match_scene.player.dive_time = 0.0
	match_scene.player.grounded = true
	match_scene.player.move_direction = 1.0
	match_scene._dive(match_scene.player)
	match_scene._update_actor(match_scene.player, 0.01)
	if not is_equal_approx(match_scene.player.velocity.x, 460.0 * float(match_scene.player.data.speed)):
		_fail("飞扑速度没有体现角色的灵活属性。")
		return

	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene.ball_position = Vector2(720, VolleyMatch.FLOOR_Y - VolleyMatch.BALL_RADIUS + 1)
	match_scene.ball_velocity = Vector2(0, 120)
	match_scene._update_ball(0.016)
	if match_scene.player_score != 1 or match_scene.player.celebrate_time <= 0.0 or match_scene.cpu.hurt_time <= 0.0:
		_fail("球落在电脑场地后我方没有得分。")
		return
	match_scene.player.block_time = 0.4
	var block_pose: Dictionary = match_scene._actor_pose(match_scene.player)
	if Vector2(block_pose.left_hand).y > -105.0 or Vector2(block_pose.right_hand).y > -105.0:
		_fail("拦网动画没有把双手举过头顶。")
		return
	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene.player.block_time = 0.0
	match_scene.player.block_cooldown = 0.0
	match_scene.player.block_fatigue = 0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.grounded = true
	match_scene._block(match_scene.player)
	if match_scene.player.block_time < 0.37 or match_scene.player.block_cooldown < 0.84 or match_scene.player.block_fatigue != 1:
		_fail("拦网没有进入冷却并累积连续使用疲劳。")
		return
	match_scene.player.position = Vector2(VolleyMatch.NET_X - 34.0, VolleyMatch.FLOOR_Y)
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.ball_position = match_scene.player.position + Vector2(0.0, -100.0)
	match_scene.ball_velocity = Vector2(-320.0, 80.0)
	match_scene._check_block_contact(match_scene.player)
	if match_scene.ball_velocity.x <= 0.0 or match_scene.ball_position.y + VolleyMatch.BALL_RADIUS >= VolleyMatch.NET_TOP:
		_fail("玩家拦网后没有把球推向对方并抬过网带。")
		return
	if not match_scene.player.block_connected or match_scene.player.action_lock_time > 0.13:
		_fail("成功拦网没有缩短动作硬直。")
		return
	match_scene.player.action_lock_time = 0.0
	var fatigue_during_cooldown := match_scene.player.block_fatigue
	match_scene._block(match_scene.player)
	if match_scene.player.block_fatigue != fatigue_during_cooldown or match_scene.action_message != "拦网冷却":
		_fail("拦网冷却期间仍能重复发动。")
		return
	var block_crossed_net := false
	for step in 20:
		match_scene._update_ball(0.01)
		if match_scene.ball_position.x > VolleyMatch.NET_X and match_scene.ball_velocity.x > 0.0:
			block_crossed_net = true
			break
	if not block_crossed_net:
		_fail("玩家拦到球后仍被球网反弹回本方。")
		return
	match_scene.player.block_time = 0.0
	match_scene.player.block_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.grounded = true
	match_scene.player.block_fatigue = 3
	match_scene._block(match_scene.player)
	if match_scene.player.block_time < 0.37 or match_scene.player.block_time > 0.39 or match_scene.player.block_reach_scale > 0.53:
		_fail("连续拦网没有缩短判定范围。")
		return
	match_scene._update_actor(match_scene.player, 0.39)
	if match_scene.player.action_lock_time < 0.37:
		_fail("拦空后没有产生0.38秒硬直。")
		return
	match_scene.player.block_time = 0.0
	match_scene.player.position = Vector2(300.0, VolleyMatch.FLOOR_Y)
	match_scene.player.velocity = Vector2.ZERO
	match_scene.player.grounded = true
	match_scene.player.hit_cooldown = 0.0
	match_scene.player.action_lock_time = 0.0
	match_scene.player.block_fatigue = 3
	match_scene.last_touch_side = 1
	match_scene.side_touches = 1
	match_scene.ball_position = match_scene._ideal_contact_position(match_scene.player, VolleyMatch.TouchPhase.RECEIVE)
	match_scene._normal_hit(match_scene.player)
	if match_scene.player.block_fatigue != 0:
		_fail("成功普通接球后没有恢复拦网手臂疲劳。")
		return
	match_scene.net_clash_cooldown = 0.0
	match_scene.player.position = Vector2(VolleyMatch.NET_X - 34.0, 300.0)
	match_scene.cpu.position = Vector2(VolleyMatch.NET_X + 34.0, 300.0)
	match_scene.player.grounded = false
	match_scene.cpu.grounded = false
	match_scene.player.spike_ready_time = 0.2
	match_scene.cpu.spike_ready_time = 0.2
	match_scene.player.spike_attempted = true
	match_scene.cpu.spike_attempted = true
	match_scene.ball_position = Vector2(VolleyMatch.NET_X, 228.0)
	match_scene.ball_velocity = Vector2(360.0, 80.0)
	var rally_before_clash := match_scene.rally_hits
	if not match_scene._check_net_clash() or match_scene.ball_velocity.y > -500.0 or match_scene.last_touch_side != 0 or match_scene.side_touches != 0 or match_scene.rally_hits != rally_before_clash + 1:
		_fail("双方同时在网口触球时没有触发对抗球。")
		return

	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene.last_touch_side = -1
	match_scene.side_touches = 3
	match_scene._register_touch(-1)
	if match_scene.cpu_score != 1:
		_fail("超过三次触球没有判给对方得分。")
		return

	match_scene.player_score = 0
	match_scene.cpu_score = 0
	match_scene.set_complete = false
	match_scene.match_complete = false
	match_scene.serve_side = -1
	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene._award_point(-1, "测试发球轮换")
	match_scene._advance_after_point()
	if match_scene.serve_side != 1:
		_fail("我方发球结束后没有轮到电脑发球。")
		return
	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene._award_point(1, "测试发球轮换")
	match_scene._advance_after_point()
	if match_scene.serve_side != -1:
		_fail("电脑发球结束后没有轮到我方发球。")
		return

	match_scene.player_score = 10
	match_scene.cpu_score = 8
	match_scene.state = VolleyMatch.MatchState.PLAY
	match_scene._award_point(-1, "测试")
	if not match_scene.set_complete or match_scene.player_sets != 1:
		_fail("11分且领先2分没有赢下一局。")
		return

	game.queue_free()
	await process_frame
	await process_frame
	print("冒烟测试通过：选人、击球、落地得分、三次触球和局分规则正常。")
	quit(0)


func _fail(message: String) -> void:
	push_error("冒烟测试失败：" + message)
	quit(1)
