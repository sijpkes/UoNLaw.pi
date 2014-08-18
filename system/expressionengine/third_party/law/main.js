
		$(document).ready(function() {
			$(".student_assess").last().closest('tr').after("<tr class='okmsg'><td colspan='3'>You have 100 points to distribute.</td>");	
			
		$("button#assess").attr('disabled','disabled');
		
		var getTotal = function() {
			var total = 0;
			$(".student_assess").removeClass('focusUser');
			$(".student_assess").each(function(i,o) {
				if(isNaN($(o).val())) {
					$(this).addClass('focusUser');
					return false;
				}
				total += Number($(o).val());
				if(total > 100) {
					$(this).addClass('focusUser');
				}
			});
			return total;
		};
		
		$(document).on('keyup', ".student_assess", function() {
				$(".errormsg,.okmsg").remove();
				var total = getTotal();
				if(!total) {
					$(".student_assess").last().closest('tr').after("<tr class='errormsg'><td colspan='3'>You can only enter numeric values for the score.</td>");
					$("button#assess").attr('disabled','disabled');
					return false;
				}
				
				var diff = Math.abs(100 - total);
				if(total > 100) {
					$(".student_assess").last().closest('tr').after("<tr class='errormsg'><td colspan='3'>You've given out "+ diff +" too many points, please amend.</td>");
					$("button#assess").attr('disabled','disabled');
				} else if(total < 100) {
					$(".student_assess").last().closest('tr').after("<tr class='okmsg'><td colspan='3'>You've got "+ diff +" points left, please distribute them.</td>");
					$("button#assess").attr('disabled','disabled');
				} else if(total == 100) {
					$(".student_assess").last().closest('tr').after("<tr class='okmsg'><td colspan='3'>You've distributed all 100 points.</td>");
					$("button#assess").removeAttr('disabled');
				} else {
				    $("button#assess").attr('disabled','disabled');
				}
		});
		
		$('.student_assess').keyup(function () { 
			this.value = this.value.replace(/[^0-9]/g,'');
		});
			
		$("button#Save").click(function() {
			$("input[name='locked']").val('0');
			$("form#assessments").submit();
		});
		
		$("button#assess").click(function(e) {
			e.preventDefault();
	
			var formok = true;
			var confirmed = false;
			$("input[type='text']").each(function() {
				var me = this;
				$(me).css('border', 'none');
				if(isNaN($(this).val())) {
					$(me).css('border', '2px solid red');
					formok = false;
				}
			});
			
			$(".comment").each(function() {
				var me = this;
				$(me).css('border', 'none');
				if($(this).val().length == 0) {
					$(me).css('border', '2px solid red');
					formok = false;
				}
			});
			
			var total = getTotal();
			if(total > 100 || total < 100) {
				$(".student_assess").last().closest('tr').after("<tr class='errormsg'><td colspan='3'>Please ensure that your scores add up to 100.</td>");
				$("button#assess").attr('disabled','disabled');
			}
			
			if(formok === true) {
				confirmed = confirm("Once you have submitted you will not be able to return to this form to amend your marks.  Are you sure?");
				if(confirmed){ 
						$("input[name='locked']").val('1');
						$("form#assessments").submit();
				}
			} else {
				alert("Please fill in all the comment fields and ensure you only enter numeric values for the score.");
			}  
		});
		
		$(".savemsg, .saveErrorMsg").fadeIn(200).fadeOut(200).fadeIn(200).delay(10000).fadeOut(200);
		
		// trigger total message
		$('.student_assess').first().trigger('keyup');
		
		});
		