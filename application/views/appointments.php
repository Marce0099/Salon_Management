<h3 class="section-title">Reservas</h3>

<link href="<?php echo base_url('assets/gentelella/vendors/fullcalendar/dist/fullcalendar.min.css'); ?>" rel="stylesheet">
<script src="<?php echo base_url('assets/gentelella/vendors/moment/min/moment.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/gentelella/vendors/fullcalendar/dist/fullcalendar.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/gentelella/vendors/fullcalendar/dist/lang/es.js'); ?>"></script>

<style>
	#calendar{ max-width: 100%; }

	.fc-day-top{ position: relative; }
	.appt-badge{
		position: absolute;
		top: 2px;
		left: 2px;
		background: #e08e0b;
		color: #fff;
		border-radius: 10px;
		font-size: 11px;
		line-height: 1;
		padding: 2px 5px;
		font-weight: bold;
		z-index: 2;
	}
</style>

<div id="calendar"></div>

<script>
var baseUrl = '<?php echo base_url(); ?>';

function loadCounts(start, end){

	$('#calendar .appt-badge').remove();

	$.getJSON(baseUrl + 'appointments/counts', {
		start: start,
		end: end
	}, function(counts){

		$.each(counts, function(date, total){

			$('#calendar .fc-day-top[data-date^="' + date + '"]').append(
				'<span class="appt-badge">' + total + '</span>'
			);
		});
	});
}

$(function(){

	$('#calendar').fullCalendar({
		locale: 'es',
		header: { left: 'prev,next today', center: 'title', right: '' },
		viewRender: function(view){
			loadCounts(view.start.format('YYYY-MM-DD'), view.end.format('YYYY-MM-DD'));
		},
		dayClick: function(date){
			window.location.href = baseUrl + 'appointments/schedule/' + date.format('YYYY-MM-DD');
		}
	});
});
</script>
