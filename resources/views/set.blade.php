<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>日曆設定</title>
  <link rel="stylesheet" href="//code.jquery.com/ui/1.10.4/themes/smoothness/jquery-ui.css">
  <script src="//code.jquery.com/jquery-1.9.1.js"></script>
  <script src="//code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
  <script src="js/jquery-ui.multidatespicker.js"></script>
  <link href='fullcalendar/lib/main.css' rel='stylesheet' />
  <script src='fullcalendar/lib/main.js'></script>
  <script src='fullcalendar/lib/locales-all.js'></script>
  <link rel="stylesheet" href="https://jqueryui.com/resources/demos/style.css">
  <link rel="stylesheet" href="css/jquery-ui.multidatespicker.css">
  <style type="text/css">
  tr{
    height: 10%;
  }
  .fc .fc-scrollgrid-section-body table{
    height: 600px !important; 
  }
  .fc .fc-view-harness {
    height: 625px !important; 
}
  </style>

  <script>
    //main calendar
    document.addEventListener('DOMContentLoaded', function() {
 

        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'zh-tw',
        });
        calendar.render();
        calendar.on('dateClick', function(info) {
            console.log('clicked on ' + info.dateStr);
        });

        var event_arr = [];
        $.ajax({
        type: 'GET',
        url: 'api/v1/loadschedule',
        success:function(datas) { 
                console.log(datas)
                datas = JSON.parse(datas);

                datas.forEach(function(value, index, array){
                    calendar.addEvent({
                        title: value['work_type'],
                        start: value['date'],
                        allDay: true
                    });
                })
                console.log(event_arr);
                // foreach( datas as data ){

                // }
                // if(data.msg =="true" ){ 
                // // view("修改成功！"); 
                // alert("修改成功！"); 
                // window.location.reload(); 
                // }else{ 
                // view(data.msg); 
                // } 
            }, 
            error : function() { 
            // view("異常！"); 
            alert("異常！"); 
            } 
        });
    });

  $(function() {
    //嘗試


    // $( "#datepicker" ).datepicker({
    //   changeMonth: true,
    //   changeYear: true,
    // });
    // $( "#datepicker1" ).datepicker({
    //   changeMonth: true,
    //   changeYear: true,
    // });
    // $( "#datepicker2" ).datepicker({
    //   changeMonth: true,
    //   changeYear: true,
    // });
    var date = new Date();
    var today = new Date();
    var y = today.getFullYear();
    var m = today.getMonth();
    
    $('#datepicker').multiDatesPicker({
        // preselect the 14th and 19th of the current month
        maxPicks: 1,
        dateFormat: "20y-m-d",
        minDate: 0, // today
	    maxDate: 31 // +30 days from today
    });

    $('#datepicker1').multiDatesPicker({
        dateFormat: "20y-m-d",
        minDate: 0, // today
        defaultDate: (y%2000)+'-'+(m+1)+'-1'
    });

    $('#mdp-demo').multiDatesPicker({
        numberOfMonths: [1,3],
        defaultDate: '1/'+m+y
    });



  });
  function show(){
    if ($("#select").val() == '1'){
        $("#datepicker_area").show();
        $("#datepicker_area1").hide();
        $('#datepicker').multiDatesPicker('resetDates', 'picked');
        $('#datepicker1').multiDatesPicker('resetDates', 'picked');
    }
    if ($("#select").val() == '2'){
        $("#datepicker_area").hide();
        $("#datepicker_area1").show();
        $('#datepicker').multiDatesPicker('resetDates', 'picked');
        $('#datepicker1').multiDatesPicker('resetDates', 'picked');
    }
  }
  function clearCal(){
    $('#datepicker').multiDatesPicker('resetDates', 'picked');
    $('#datepicker1').multiDatesPicker('resetDates', 'picked');
  }
  function pushDates(){
   if($("#scheduleList").val() == ''){
       alert("請選擇班別");
       return;
   }
   if($("#select").val() == '1'){
    var dates = $('#datepicker').multiDatesPicker('getDates');
   }
   if($("#select").val() == '2'){
    var dates = $('#datepicker1').multiDatesPicker('getDates');
   }
   $.ajax({
    type: 'POST',
    url: 'api/v1/postdata',
    data: {
        "dates": dates,
        "schedule_type" : $("#scheduleList").val()
    },
    success:function(data) { 
            console.log(data);
            // if(data.msg =="true" ){ 
            // // view("修改成功！"); 
            alert("修改成功！"); 
            window.location.reload(); 
            // }else{ 
            // view(data.msg); 
            // } 
        }, 
        error : function() { 
        // view("異常！"); 
        alert("異常！"); 
        } 
  });
  }
  </script>
</head>
<body>
    <select id="select" onchange="show()">
        <option value="1">單日設定<option>
        <option value="2">批量設定<option>
        <option value="3">當前班表<option>
    </select>
    <div style="display:inline-block;width:100%;" >
        <div id="datepicker_area" >
            單日設定：
            <input type="text" id="datepicker" style="width:100%;">
        </div>
        <div id="datepicker_area1" style="display:none;">
            批量設定：
            <input type="text" id="datepicker1" style="width: 50%;">
        </div>
        <select id="scheduleList">
            <option value="">無<option>
            <option value="B">B<option>
            <option value="C">C<option>
            <option value="J">J<option>
            <option value="N">N<option>
        </select>
        <button onclick="pushDates()">送出</button>
        <button onclick="clearCal()">清空</button>
    </div>
    <!-- <div id="mdp-demo" style="height: 600px;"></div> -->
    <div style="width:90%;height:600px;">
        <div id='calendar'></div>
    </div>
</body>
</html>
















<?php /**PATH J:\LineBot Commad System\project\resources\views/set.blade.php ENDPATH**/ ?>