<?php

/**
 * Homepage of HOD and faculty level users, provides interfaces to add/delete courses
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
  <script type="text/javascript" src="js/jquery.min.js" ></script>
  <script type="text/javascript" src="js/form.js"></script>
  <script type="text/javascript" src="js/chosen.js"></script>
  <script type="text/javascript">
  $(function()
  {
      $("#main_menu a").each(function() {
          if($(this).prop('href') == window.location.href || window.location.href.search($(this).prop('href'))>-1)
          {
              $(this).parent().addClass('current');
              document.title+= " | " + this.innerHTML;
              return false;
          }
      })
      $("option[value=<?=$_SESSION['faculty']?>]").attr('selected', 'selected');
      $("select").chosen();
      $("#faculty").change(function(){
        window.location.href='faculty.php?faculty='+this.value;
      });
      $("#courseSelect").change(function(){
        var value = $(this).val();
        var node = $(this).children('option[value="' + value + '"]')[0];

        var courseName = $(node).attr('data-coursename');
        var rCount = $(node).attr('data-rcount');
        var cType = $(node).attr('data-type');

        $(this).siblings('input[name="cName"]').val(courseName);
        $(this).siblings('input[name="cRCount"]').val(rCount);
        $(this).siblings('input[name="cType"]').val(cType);
      });

      $('#topmenu').click(function() {
        $('#nav_bar').toggle();
      });

      $(document).click(function(e) {
        if (e.target.id != 'nav_bar' && e.target.id != 'topmenu') {
          $('#nav_bar').hide();
        }
      });

      $("#userIcon").click(function() {
        $("#userMenu").toggle();
      });
  })
  </script>
</head>
<body>
  <div id="header">
    <div id="account_info">
      <div class="infoTab">
        <div class="fixer"></div>
        <div id="fName"><?=$_SESSION['fName']?></div>
        <div class="dashIcon usr" id="userIcon" style="cursor:pointer;"></div>
        <div id="userMenu">
          <p><a href="/logout.php">Logout</a></p>
        </div>
      </div>
    </div>
    <div id="header_text" style="box-sizing:border-box;padding:5px;">
      <img id="topmenu" src="images/information.png" style="height:30px;width:auto;float:left;margin-top:3px;margin-left:15px;cursor:pointer;"></img>
      <p style="float:left;margin-top:-5px;margin-left:15px;">QuickSlots</p>
    </div>
  </div>
  <div id="shadowhead">Manage Courses</div>
  <div id="nav_bar">
    <ul class="main_menu" id="main_menu">
    <?php
      if(sessionCheck('level','dean'))
        echo '<li class="limenu"><a href="dean.php">Manage Timetables</a></li>
              <li class="limenu"><a href="manage.php?action=departments">Manage Departments</a></li>
              <li class="limenu"><a href="manage.php?action=faculty">Manage Faculty</a></li>
              <li class="limenu"><a href="manage.php?action=batches">Manage Batches</a></li>
              <li class="limenu"><a href="manage.php?action=rooms">Manage Rooms</a></li>
              <li class="limenu"><a href="manage.php?action=slot_groups">Manage Slot Groups</a></li>';
    ?>
            <li class="limenu"><a href="faculty.php">Manage Courses</a></li>
            <li class="limenu"><a href="addpreference.php">Manage Preferences</a></li>
    <?php
    if(sessionCheck('level','dean'))
      echo '<li class="limenu"><a href="allocate.php">Allocate Timetable</a></li>';
    ?>
            <li class="limenu"><a href="./">View Timetable</a></li>
    </ul>
  </div>
  <div id="content">
    <?php if($_SESSION['level']!="faculty") : ?>
      <div class="title" style="padding-bottom: 20px">
      <span class="inline" style="vertical-align: middle;padding:10px 0 0 10px">Faculty:</span>
        <select name="fac_id" id="faculty"  data-placeholder="Choose Faculty...">
          <?php
            $query = $db->prepare('SELECT * FROM faculty where dept_code=?');
            $query->execute([$_SESSION['dept']]);
            foreach($query->fetchall() as $fac)
              echo "<option value=\"{$fac['uName']}\">{$fac['fac_name']} ({$fac['uName']})</option>";
          ?>
        </select>
      </div>
    <?php endif; ?>
    <div class="box">
        <div class="boxbg"></div>
        <div class="information"><div class="add icon"></div></div>
        <div class="title">Add  Course</div>
        <div class="elements">
          <form method="post" action="courses.php?action=add">
            <input type="text" name="cId" class="styled details" required pattern="[^ :]{2,20}" title="2 to 20 characters without spaces" placeholder="Course ID" />
            <input type="text" name="cName" class="styled details" required pattern=".{6,100}" title="6 to 100 characters" placeholder="Course Name" />
            <input type="text" name="cRCount" class="styled details" required pattern="[0-9]+" title="6 to 100 characters" placeholder="Count of students" />
            <select name="batch[]" id="allowed" multiple="" class="stretch"  data-placeholder="Allowed Batches..." required>
              <?php
              foreach($db->query('SELECT * FROM batches') as $batch)
                echo "<option value=\"{$batch['batch_name']} : {$batch['batch_dept']}\">{$batch['batch_name']} : {$batch['batch_dept']} ({$batch['size']})</option>";
              ?>
            </select>
            <select name="cType" class="stretch"  data-placeholder="Course Type..." required>
              <option label="Choose Course Type..."></option>
              <option value="core">Core</option>
              <option value="minor">Minor</option>
              <option value="lab">Lab</option>
              <option value="laca">LA/CA</option>
              <option value="other">Other</option>
            </select>
            <div class="left">
              <input type="checkbox" class="styled" id="allowConflict" value="1" name="allowConflict">
              <label for="allowConflict">Allow conflicting allocations</label>
            </div>
            <div class="blocktext info"></div>
            <div class="center button">
                <button>Add</button>
            </div>
          </form>
        </div>
    </div>
    <div class="box">
        <div class="boxbg"></div>
        <div class="information"><div class="icon remove"></div></div>
        <div class="title">Update Course</div>
        <div class="elements">
          <form method="post" action="courses.php?action=update">
            <select name="cId" id="courseSelect" class="updateSelect stretch" data-placeholder="Choose Course..." required>
              <option label="Choose Course..."></option>
              <?php
              $query = $db->prepare('SELECT * FROM courses WHERE fac_id = ?');
              $query->execute([$_SESSION['faculty']]);

              while($course = $query->fetch())
                echo "<option value=\"{$course['course_id']}\" data-coursename=\"{$course['course_name']}\" data-rcount=\"{$course['registered_count']}\" data-type=\"{$course['type']}\">{$course['course_name']} ({$course['course_id']})</option>"
              ?>
            </select>
            <input type="text" name="cName" class="styled details" required pattern=".{6,100}" title="6 to 100 characters" placeholder="Course Name" />
            <input type="text" name="cRCount" class="styled details" required pattern="[0-9]+" title="6 to 100 characters" placeholder="Count of students" />
            <select name="cType" class="stretch"  data-placeholder="Course Type..." required>
              <option label="Choose Course Type..."></option>
              <option value="core">Core</option>
              <option value="minor">Minor</option>
              <option value="lab">Lab</option>
              <option value="laca">LA/CA</option>
              <option value="other">Other</option>
            </select>
            <div class="left">
              <input type="checkbox" class="styled" id="updateAllowConflict" value="1" name="updateAllowConflict">
              <label for="updateAllowConflict">Allow conflicting allocations</label>
            </div>
            <div class="blocktext info"></div>
            <div class="center button">
              <button>Update</button>
            </div>
          </form>
        </div>
    </div>
    <div class="box">
        <div class="boxbg"></div>
        <div class="information"><div class="icon remove"></div></div>
        <div class="title">Delete Course</div>
        <div class="elements">
          <form method="post" action="courses.php?action=delete" class="confirm">
            <select name="cId" class="updateSelect stretch" data-placeholder="Choose Course..." required>
              <option label="Choose Course..."></option>
              <?php
              $query = $db->prepare('SELECT * FROM courses where fac_id = ?');
              $query->execute([$_SESSION['faculty']]);
              while($course = $query->fetch())
                echo "<option value=\"{$course['course_id']}\">{$course['course_name']} ({$course['course_id']})</option>"
              ?>
            </select>
            <input type="hidden" id="confirm_msg" value="Are you sure you want to delete the selected course?">
            <div class="blocktext info"></div>
            <div class="center button">
              <button>Delete</button>
            </div>
          </form>
        </div>
    </div>
  </div>
</body>
</html>
