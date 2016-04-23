<?php

/**
 * Provides interface and back end routines for allocation of courses to time slots
 * @author Avin E.M; Kunal Dahiya
 */

require_once('functions.php');
if(!sessionCheck('logged_in'))
{
  header("Location: ./login.php");
  die();
}
require_once('connect_db.php');
if(!isset($_SESSION['faculty']))
  $_SESSION['faculty'] = $_SESSION['uName'];
if(!sessionCheck('level','faculty'))
{
  if(!empty($_GET['faculty']))
  {
    $query = $db->prepare('SELECT uName FROM faculty where uName = ? AND dept_code=?');
    $query->execute([$_GET['faculty'],$_SESSION['dept']]);
    $fac = $query->fetch();
    if(!empty($fac['uName']))
      $_SESSION['faculty'] = $_GET['faculty'];
  }
}
$query = $db->prepare('SELECT * FROM courses where fac_id = ?');
$query->execute([$_SESSION['faculty']]);
$courses = $query->fetchall();
foreach ($courses as $course) {
  if($course['allow_conflict'] && $current['allowConflicts'])
    continue;
  $blocked[$course['course_id']] = [];
  $filter = !$current['allowConflicts']?"OR allow_conflict=1":"";
  $query = $db->prepare("SELECT course_id,count(*) as batches FROM
      (SELECT * FROM allowed where course_id NOT IN
      (SELECT course_id FROM courses where fac_id = ? $filter)) other NATURAL JOIN
      (SELECT batch_name,batch_dept FROM allowed where course_id=?) batches
       group by course_id");

  $query->execute([$_SESSION['faculty'],$course['course_id']]);

  $conflicts = $query->fetchall();

  foreach ($conflicts as $conflict)
  {
    $query = $db->prepare('SELECT day,slot_num FROM slot_allocs where table_name=? AND course_id=?');
    $query->execute([$current['table_name'],$conflict['course_id']]);
    $conf_slots=$query->fetchall();
    foreach ($conf_slots as $conf_slot)
    {
      $slotStr = $conf_slot['day']. "_" .$conf_slot['slot_num'];
      if(isset($blocked[$course['course_id']][$slotStr]))
          $blocked[$course['course_id']][$slotStr] += $conflict['batches'];
      else
          $blocked[$course['course_id']][$slotStr] = $conflict['batches'];
    }
  }
}

if(valueCheck('action','saveSlots'))
{
  if($current['frozen'])
    postResponse("error","This timetable has been frozen");
  foreach ($_POST as $slotStr => $course_room)
  {
    $course=explode(':', $course_room)[0];
    if(!empty($blocked[$course][$slotStr]))
      postResponse("redirect","allocate.php?error=conflict");
  }
  $query = $db->prepare('DELETE FROM slot_allocs where table_name=? AND course_id IN (SELECT course_id FROM courses where fac_id=?)');
  $query->execute([$current['table_name'],$_SESSION['faculty']]);
  $query = $db->prepare('INSERT INTO slot_allocs values(?,?,?,?,?)');
  try
  {
    foreach ($_POST as $slotStr => $course_room)
    {
      $course_room = explode(':', $course_room);
      $course = $course_room[0];
      $room = $course_room[1];
      $slot = explode('_', $slotStr);
      $query->execute([$current['table_name'],$slot[0],$slot[1],$room,$course]);
    }
  }
  catch(PDOException $e)
  {
    if($e->errorInfo[0]==23000)
      postResponse("error","The selected room has been booked already, rooms list has been refreshed");
    else
      postResponse("error",$e->errorInfo[2]);
  }
  postResponse("info","Slots Saved");
  die();
}
if(valueCheck('action','queryRooms'))
{
    $slot = explode('_', $_POST["slot"]);
    $query = $db->prepare('SELECT min(size) FROM allowed NATURAL JOIN batches where course_id=?');
    $query->execute([$_POST['course']]);
    $minCap = $query->fetch()[0];
    $query = $db->prepare('SELECT room_name,capacity FROM rooms
             where capacity>=? AND room_name NOT IN
             (SELECT room FROM slot_allocs where table_name=? AND day=? AND slot_num=?
              AND course_id NOT IN (SELECT course_id FROM courses where fac_id=?)
              ) ORDER BY capacity');
    $query->execute([$minCap,$current['table_name'],$slot[0],$slot[1],$_SESSION['faculty']]);
    $rooms = $query->fetchall(PDO::FETCH_NUM);
    die(json_encode($rooms));
}
if(valueCheck('action','queryConflict'))
{
    $slot = explode('_', $_POST["slot"]);
    $query = $db->prepare('SELECT course_id,course_name,fac_id,fac_name,GROUP_CONCAT(CONCAT(batch_name,\' : \',batch_dept) ORDER BY batch_name SEPARATOR \', \') as batches from allowed NATURAL JOIN courses NATURAL JOIN (SELECT fac_name,uName as fac_id from faculty) faculty where course_id IN (SELECT course_id from slot_allocs where table_name=? AND day=? AND slot_num=?) AND (batch_name,batch_dept) IN (SELECT batch_name,batch_dept FROM allowed where course_id=?) GROUP BY course_id');
    $query->execute([$current['table_name'],$slot[0],$slot[1],$_POST['course']]);
    $conflicts = $query->fetchall();
    $inf_html = "";
    foreach ($conflicts as $conflict) {
      $fac_info = $conflict['fac_name'];
      if(!sessionCheck('level','faculty'))
        $fac_info = "<a href=\"allocate.php?faculty={$conflict['fac_id']}\">{$conflict['fac_name']}</a>";
      $inf_html .= <<<HTML
      <tr class="data">
          <td class="course_name">{$conflict['course_name']}</td>
          <td class="faculty">$fac_info</td>
          <td class="batch">{$conflict['batches']}</td>
      </tr>
HTML;
  }
  die($inf_html);
}

?>
<!DOCTYPE HTML>
<html>
<head>
  <title>QuickSlots</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <link rel="shortcut icon" type="image/png" href="images/favicon.png"/>
  <link rel="stylesheet" type="text/css" href="css/styles.css">
  <link rel="stylesheet" type="text/css" href="css/dashboard.css">
  <link rel="stylesheet" type="text/css" href="css/chosen.css">
  <link rel="stylesheet" type="text/css" href="css/table.css">
  <script type="text/javascript" src="js/jquery.min.js" ></script>
  <script type="text/javascript" src="js/ui.min.js" ></script>
  <script type="text/javascript" src="js/ui-touch-punch.min.js" ></script>
  <script type="text/javascript" src="js/form.js"></script>
  <script type="text/javascript" src="js/chosen.js"></script>
  <script type="text/javascript" src="js/grid.js"></script>
  <script>

  $(function()
  {
    $("#main_menu a").each(function() {
      if($(this).prop('href') == window.location.href || window.location.href.search($(this).prop('href'))>-1)
      {
          $(this).parent().addClass('current');
          document.title+= " | " + this.innerHTML;
          return false;
      }
    });
    $("option[value='<?=$current['table_name']?>']","#table_name").attr('selected','selected');
    <?php
      $t=$current['start_hr'] .":". $current['start_min'] ." ". $current['start_mer'];
      echo"drawGrid('{$current['table_name']}',{$current['slots']},{$current['days']},{$current['duration']},'$t');";
    ?>
    $(".course").draggable({
      helper:"clone",
      opacity: 0.7,
      appendTo: "#rightpane",
      tolerance: "fit",
      start: function(e,ui)
      {
        var blocked = $("."+this.id,".blocked");
        resetInfo();
        $("input",blocked).each(function(){
            var cell=$("#"+this.name);
                cell.addClass('conflicting');
            $.data(cell[0],"content",cell.html());
            cell.html(this.value);
        })
      },
      stop: function(){
        $(".conflicting").each(function(){
            if($(this).hasClass('showInfo'))
              return;
            if(this.innerHTML)
                $(this).html($.data(this,"content"));
            $(this).removeClass('conflicting');
        });
      }
    });
    $(".cell","#timetable").click(function(){
      if(!this.innerHTML || $(this).hasClass('conflicting'))
        return false;
      $(".selected").removeClass('selected');
      $(this).addClass('selected');
      resetInfo();
      $("#roomSelect").html('<div class="center button"></div>');
      $.ajax({
        type: "POST",
        url: "allocate.php?action=queryRooms",
        data: "slot="+this.id+"&course="+$("input[name="+this.id+"]","#courseAlloc").val().split(':')[0],
        success: function(result)
        {
            $("#roomSelect").html('<select name="room_name" style="width:150px" class="updateSelect"  data-placeholder="Choose Room..." required onchange="assignRoom(this.value)">');
            var roomSelect=$("select[name=room_name]"),
            rooms=JSON.parse(result);
            for(i=0;i<rooms.length;i++)
              roomSelect.append('<option value="' + rooms[i][0] +'">'+rooms[i][0]+ ' (' + rooms[i][1] +')</option>');
            var current = $(".selected").attr('id');
            if(current)
            {
              var current_room = $("input[name="+ current +"]","#courseAlloc").val().split(':')[1];
              if(current_room && current_room!="undefined")
                $("option[value='"+ current_room + "']",roomSelect).attr("selected", "selected");
              else
              {
                roomSelect.prop("selectedIndex", 0)
              }
              roomSelect.change();
              roomSelect.chosen();
            }
            else
              roomSelect.remove();
        }
      });
    })
    $("button").click(function(){$(".selected").click()}) // Refresh Room list on submit
    var active = $(".cell","#timetable").not(".disabled,.blank,.day,.time");
    active.droppable(
    {
        drop: function(e,ui)
        {
        <?php
          if(!$current["allowConflicts"]):
        ?>
          if($(this).hasClass('conflicting'))
          {
            $(this).removeAttr('style');
            $(this).addClass('showInfo');
            changes = true;
            $("input[name="+this.id+"]","#courseAlloc").remove();
            $("#conflict_help").html('<div class="center button"></div>');
            $.ajax({
              type: "POST",
              url: "allocate.php?action=queryConflict",
              data: "slot="+this.id+"&course="+ui.draggable[0].id,
              success: function(data)
              {
                $("#conflict_help").hide();
                $("#conflict_info").append(data);
              }
            })
            return;
          }
        <?php
          endif;
        ?>
          var i = ui.draggable.index()%colors.length;
          var inner = $('<div class="course_holder"></div>');
          $(this).html(inner);
          $.data(this,"content",inner);
          inner.html(ui.draggable.html());
          $(this).css('background-color',colors[i][0]);
          $(this).css('box-shadow','0 0 25px ' +colors[i][1]+ ' inset');
          $("input[name="+ this.id +"]","#courseAlloc").remove();
          changes = true;
          $("#courseAlloc").append('<input type="hidden" name="'+ this.id +'" value="'+ ui.draggable[0].id +":" + $("select[name=room_name]").val() +'">')
          $(this).click();
        },
        over: function(e,ui){
            var i= ui.draggable.index()%colors.length;
            if(!this.innerHTML)
            {
                $(this).css('background-color',colors[i][0]);
                $(this).css('box-shadow','0 0 25px ' +colors[i][1]+ ' inset');
            }
        },
        out: function(){
            if(!this.innerHTML)
                $(this).removeAttr('style');
        }
    });
    active.dblclick(function()
    {
        $(this).removeClass('selected');
        $(this).html('');
        $(this).removeAttr('style');
        $("input[name="+this.id+"]","#courseAlloc").remove();
    })
    $("input","#courseAlloc").each(function(){
        var slot = $("#"+this.name),
            inner = $('<div class="course_holder"></div>'),
            course = $("#"+this.value.split(':')[0].replace('/','\\/')),
            i=course.index()%colors.length;
        slot.html(inner);
        inner.html(course.html());
        slot.css('background-color',colors[i][0]);
        slot.css('box-shadow','0 0 25px ' +colors[i][1]+ ' inset');
    })
    colorCourses();
    $("option[value=<?=$_SESSION['faculty']?>]","#faculty").attr('selected','selected');
    $("#table_name").chosen();
    $("#faculty").chosen().change(function(){
      window.location.href='allocate.php?faculty='+this.value;
    })
    $("#table_name").change(function(){
      window.location.href='allocate.php?table='+this.value;
    })
  })
  function assignRoom(room)
  {
    var slotId=$(".selected")[0].id;
    var slot=$("input[name=" + slotId + "]")[0];
    slot.value = slot.value.split(":")[0]+":"+room;
  }
  function resetInfo()
  {
    $(".showInfo").html('');
    $("tr.data").remove();
    $("#conflict_help").html('&#9679; Drop a course into a conflicting slot to show conflict details');
    $("#conflict_help").show();
    $(".showInfo").removeClass('showInfo conflicting');
  }
  var changes = false;
  window.onbeforeunload = function(e) {
    message = "There are unsaved changes in the timetable, are you sure you want to navigate away without saving them?.";
    if(changes)
    {
      e.returnValue = message;
      return message;
    }
  }
 $( document ).ready(function() {
    // var dayTime = document.getElementBy
 		//Event Listener handler
   	var setSlots = function(parentId){
   		if(parentId=="everyone"){
   			$("input[type='radio'][value='anytime']").attr('checked', true);
   		}

   		// console.log("here");
        var optionNode = $("#courseId").children('option[value="' + $("#courseId").val() + '"]')[0];
        courseType = $(optionNode).attr('data');
        // console.log(courseType);

        var slots;
        if(parentId=="everyone"){
            slots = $("[id^='course_slot'");
        }
        else{
            if(parentId == 'preference1')
                slots = $("#"+parentId).find("#course_slot1");
            else if(parentId == 'preference2')
                slots = $("#"+parentId).find("#course_slot2");
            else
                slots = $("#"+parentId).find("#course_slot3");
        }

        if(courseType=="nothing"){
            console.log("nothing")
            slots.html('');
            slots.append('<option value="select_slot">Select Slot</option>');
            return true;
        }
        console.log(parentId);
        if(parentId=="everyone")
            var sessions = $("input[type='radio']");
        else
            var sessions = $('#' + parentId).find("input[type='radio']");

        var session_value;
        for(var i = 0; i < sessions.length; i++){
            if(sessions[i].checked){
                session_value = sessions[i].value;
            }
        }
        console.log(session_value);


        console.log(slots);
        slots.html('');
        slots.append('<option value="select_slot">Select Slot</option>');
        // slots.innerHTML = '<option value="select_slot">Select Slot</option>';
        for(var i = 0; i < allSlots.length; i++) {
            var singleSlot = allSlots[i];
            if( courseType == 'lab' && allSlots[i].lab == 1) { //checking if it is a lab course or not
                if(session_value == 'morning' && allSlots[i].tod == 'morning') {
                    slots.append( '<option value = "' + allSlots[i].id + '"> ' + allSlots[i].id + '</option>');
                } else if(session_value == 'evening' && allSlots[i].tod == 'evening') {
                    slots.append( '<option value = "' + allSlots[i].id + '"> ' + allSlots[i].id + '</option>');
                } else if(session_value == 'anytime'){
                    slots.append( '<option value = "' + allSlots[i].id + '"> ' + allSlots[i].id + '</option>');
                }
            } else if( allSlots[i].lab == 0) { //checking
                if(session_value == 'morning' && allSlots[i].tod == 'morning') {
                    console.log("here");
                    slots.append( '<option value = "' + allSlots[i].id + '"> ' + allSlots[i].id + '</option>');
                } else if(session_value == 'evening' && allSlots[i].tod == 'evening') {
                    slots.append( '<option value = "' + allSlots[i].id + '"> ' + allSlots[i].id + '</option>');
                } else if(session_value == 'anytime'){
                    slots.append( '<option value = "' + allSlots[i].id + '"> ' + allSlots[i].id + '</option>');
                }
            }
        }
   	}
    $("#courseId").change(function(){setSlots("everyone");});
 // 	// Handler for .ready() called.
 // 		//First try using addEventListener, the standard method to add a event listener:
 // 	if(courseId.addEventListener){
 // 		console.log("addEventListener");
 // 	  courseId.addEventListener("change", function(){setSlots("everyone");}, false);
 // 	}
 // 	//If it doesn't exist, try attachEvent, the IE way:
 // 	else if(courseId.attachEvent){
 // 		console.log("attachEvent");
 // 	  courseId.attachEvent("onchange", function(){setSlots("everyone");});
 // 	}
 // 	//Just use onchange if neither exist
 // 	else{
 // 		console.log("None");
 // 		courseId.onchange = function(){setSlots("everyone");};
 // 	}
    $('input[type=radio]').click(function(){
        var parentId = this.parentNode.id;
        console.log(parentId);
        setSlots(parentId);
    });


 });

  </script>
</head>

<body style="min-width: 1347px;">
  <div id="shadowhead">Add Preferences</div>
  <div id="header">
    <div id="account_info">
      <div class="infoTab"><div class="fixer"></div><div class="dashIcon usr"></div><div id="fName"><?=$_SESSION['fName']?></div></div>
      <div class="infoTab"><div class="fixer"></div><a href="logout.php" id="logout"><div class="dashIcon logout"></div><div>Logout</div></a></div>
    </div>
    <div id="header_text">QuickSlots v1.0</div>
  </div>
  <div id="nav_bar">
    <ul class="main_menu" id="main_menu">
    <?php
    if(sessionCheck('level','dean'))
      echo '<li class="limenu"><a href="dean.php">Manage Timetables</a></li>
            <li class="limenu"><a href="manage.php?action=departments">Manage Departments</a></li>
            <li class="limenu"><a href="manage.php?action=faculty">Manage Faculty</a></li>
            <li class="limenu"><a href="manage.php?action=batches">Manage Batches</a></li>
            <li class="limenu"><a href="manage.php?action=rooms">Manage Rooms</a></li>';
    ?>
            <li class="limenu"><a href="faculty.php">Manage Courses</a></li>
            <?php
              if(!sessionCheck('level', 'dean'))
               echo '<li class="limenu"><a href="addpreference.php">Add Preferences</a></li>';
             ?>
            <li class="limenu"><a href="allocate.php">Allocate Timetable</a></li>
            <li class="limenu"><a href="./">View Timetable</a></li>
    </ul>
  </div>
  <div id = "content" >
      <form name = "preferencesForm" action = "preferences.php" method = "post" class="confirm">
          <span class="inline" style="vertical-align: middle;padding-top:10px">Course ID</span>
          <select id = "courseId" name="course_id" style="width: 170px">
          	<option value="select_course" data="nothing">Select Course</option>
              <?php
                $query = $db->prepare('SELECT course_id,type FROM courses WHERE fac_id = ?');
                $query -> execute([$_SESSION['faculty']]);
                while($course_id = $query->fetch()) {
                    echo '<option value="'.$course_id['course_id'].'" data="'.$course_id['type'].'">'.$course_id['course_id'].'</option>';
                }
              ?>
          </select> <br/>
          <?php
            echo '<script>  var allSlots = new Array();';
            foreach($db->query('SELECT id, lab, tod FROM slot_groups')as $all_slots)
            {
                    echo 'allSlots.push({id:"'.$all_slots['id'].'", lab:"'.$all_slots['lab'].'", tod:"'.$all_slots['tod'].'"});';
            }
            echo '</script>';
           ?>
          <div class = " preferencesClass" id = "preference1">
              <span class="inline" style="vertical-align: middle;padding-top:10px">Time of the Day</span>
              <input type = "radio" name = "session1" value = "anytime" checked>Any Time
              <input type="radio" name="session1" value="morning" > Morning
              <input type="radio" name="session1" value="evening"> Evening <br/>
              <span class="inline" style="vertical-align: middle;padding-top:10px">Preference 1</span>

              <select id = "course_slot1" name="course_slot1" style="width: 170px">
              	<option value="select_slot">Select Slot</option>

              </select> <br/>
          </div>
          <div class = " preferencesClass" id = "preference2">
              <span class="inline" style="vertical-align: middle;padding-top:10px">Time of the Day</span>
              <input type = "radio" name = "session2" value = "anytime" checked>Any Time
              <input type="radio" name="session2" value="morning" > Morning
              <input type="radio" name="session2" value="evening"> Evening <br/>
              <span class="inline" style="vertical-align: middle;padding-top:10px">Preference 2</span>

              <select id = "course_slot2" name="course_slot2" style="width: 170px">
                <option value="select_slot">Select Slot</option>

              </select> <br/>
          </div>
          <div class = " preferencesClass" id = "preference3">
              <span class="inline" style="vertical-align: middle;padding-top:10px">Time of the Day</span>
              <input type = "radio" name = "session3" value = "anytime" checked>Any Time
              <input type="radio" name="session3" value="morning" > Morning
              <input type="radio" name="session3" value="evening"> Evening <br/>
              <span class="inline" style="vertical-align: middle;padding-top:10px">Preference 3</span>
              <select id = "course_slot3" name="course_slot3" style="width: 170px">
                <option value="select_slot">Select Slot</option>

              </select> <br/>
        </div>
        <p class="info"></p>
        <button type="submit" value="Submit">Submit</button>
      </form>
  </div>
</body>
</html>
