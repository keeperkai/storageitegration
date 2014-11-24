function showforgotpw() {
  $('#forgotpwwindow').dialog('open');
}
function showregister() {
  $('#registerwindow').dialog('open');
}
function submitregister() {
  $.post('../../users/register', {
    account: $('#registeraccount').val(),
    password: $('#registerpw').val(),
    confirm: $('#registerpwconfirm').val(),
  },
  function(data, status) {
    if (status == 'success') {
      if (data.status == 'success') {
        $('#registerwindow').dialog('close');
        alert('註冊成功，請輸入帳號密碼');
      } else {
        alert(data.data);
      }
    } else {
      alert('註冊連線失敗');
    }
  });
}
function submitforgotpw() {
  $.post('../../users/forgotpw', {
    account: $('#forgotpwaccount').val()
  },
  function(data, status) {
    if (status == 'success') {
      if (data.status == 'success') {
        alert('已經將Hash寄到該帳號綁定之信箱，請將Hash複製到下面的欄位，並且設定新密碼');
      } else {
        alert(data.data);
      }
    } else {
      alert('連線失敗');
    }
  });

}
function resetwithhash() {
  $.post('../../users/resetwithhash', {
    account: $('#forgotpwaccount').val(),
    hash: $('#forgotpwhash').val(),
    password: $('#forgotpwnewpass').val()
  },
  function(data, status) {
    if (status == 'success') {
      if (data.status == 'success') {
        $('#forgotpwwindow').dialog('close');
        alert('已經將密碼重設，請按照正常程序登入');
      } else {
        alert(data.data);
      }
    } else {
      alert('連線失敗');
    }
  });

}


/**
 * Initialization for the js objects.
 */
$(document).ready(function() {
  $('#forgotpwwindow').dialog({
    autoOpen: false,
    height: 'auto',
    width: 'auto',
    modal: true
  });
  $('#registerwindow').dialog({
    autoOpen: false,
    height: 'auto',
    width: 'auto',
    modal: true
  });
});

