---
layout: post
tagline:  ""
title: "Мониторинг подключения USB устройств в Windows"
date:   2014-10-14 23:56:56
category: posts
tags : [WIN,ScriptFu]
wordpress_id : 1001
---

Назрела интересная задачка -- при подключении или отключении конкретного USB-устройства в Windows XP выполнять внешнюю программу. Кому интересно прошу под кат.

В одной организации есть комп, на котором крутится программка, требующая для работы HASP-донгл, при том, требует его постоянно. Вроде-бы рядовое явление, но в один прекрасный момент потребовалось, что-бы ключ ежедневно изымался из компа и складывался в сейф для пущей сохранности. И тут всплыл целый ворох проблем: при изъятии ключа программа блокировалась (красным по белому писала, что ей HASP нужен), а при возврате ключика на место, работать она не начинала -- требовалось перезапустить программу. Перезапуск проблема небольшая, пара щелчков мышкой. Но у компа ни клавы ни монитора нет: пара разъёмов в стене, привет из 90х так сказать. Роль перезапускающего отводилась мне. Нет ничего более ужасного, чем рутинная деятельность. Пока применить смекалку.

#Проблема
Всё выше описанное можно свести фактически к одной проблеме:

+ Программа Х не может продолжать работу при пропадании HASP ключа.

Нужно это несчастной как-то помочь. Программу переписать никто не даст, эмулировать HASP желания тоже не было, значит работать придётся "топором" из вне: прибивать прогу если ключи изъяли и запускать её снова при обратном действии. Благо, без ключа программа сама файлы с которыми работает закрывает, прибивание процесса не критично для данных.

#Задача
Коротко и более формально данный клинический случай можно описать в виде следующей задачи:

+ Отслеживать состояние конкретного USB устройства, и при изменении его состояния запускать предписанный скрипт

#Реализация
Собственно, что-же делать? 
Самый первый вариант, пришедший мне в голову, это погуглить софт, наверняка кто то с данной проблемой сталкивался и решал её, живём то в эпоху постмодерна -- всё придумано до нас... 

Действительно всё уже придумано, но вот незадача: придумано под Linux в основном. Но если бы всё дело было под линухами, я-б давно запилил маленький скрипт и пускал бы его по событию от udev, или мониторил бы /sys/bus/usb/devices . Наверняка вариантов ещё больше, но все они не подходят -- у меня Windows.

Ладно, бывает. Я уточнил запрос Гуглу, что б там винда фигурировала, но всё на что я натыкался было связано с флешками и запихиванием в них autorun.inf. Совсем не то, у меня не шлешка. Ещё полистав кучку разны форумов, я наконец набрёл на что-то нужное мне: [один трудяга](http://www.pcguide.com/vb/showthread.php?70158-Automatically-run-a-batch-file-on-USB-device-insertion-removal) запилил программку USBTrigger. Вроде-бы вот оно счастье, сейчас всё будет работать. Скачал, запустил, вставил донгл, его ID радостно отобразился в окошке USBTrigger. Написал батник который будет запускаться данным поделием. Всё было готово к первому тесту. Вытащил ключик. И ничего не произошло. Повторил процедуру раз пять, перепроверил батник -- теже яйца -- ничего не работает.
Я влип. Видать малой кровью не отделаться, и придётся вспоминать давно утерянный в веках своей памяти секрет написания программ на C++ и WinAPI. Чёрная магия и сатанизм для набожно ленивого админа. Гугл как назло [ссылками](http://stackoverflow.com/questions/4078909/detecting-usb-insertion-removal-events-in-windows-using-c) на Stack Overflow стращал, где мелькали дьяволькие строчки кода.

Но видать, боги бубна отвадили -- несколькими щелчками позже наткнулся на [удивительно простой пример](http://vbacodesamples.blogspot.ru/2011/07/usb-device-insertremove-monitoring-with.html) слежения за событиями USB-устройств на WSH(VBS) через механизм WMI. Действительно, как же я сам не додумался, WMI использую частенько, но для таких целей, да и что бы всё само асинхронно инициировалось... учить матчасть надо. Найденный скрипт со своей задачей справлялся идеально. Но был на мой взгляд не универсален: он мог работать только с одним устройством, и идентификация устройства производилась по его имени, а не по конкретному VID PID.

Осталось применить админские навыки копипасты и родить скрипт выполняющий поставленную задачу с желаемыми доработками. 

{% highlight vb linenos=table %}
Set objWMIService = GetObject("winmgmts:{impersonationLevel=impersonate}\\.\root\cimv2") 

'USB insert/remove hook procedure
Sub event_OnObjectReady( objEvent, objContext )

 With objEvent
   Dev = Left(.TargetInstance.DeviceId,InStrRev(.TargetInstance.DeviceId,"\")-1)
   If deviceIDs.Exists(Dev) Then
   WScript.Echo date(),time(),"Device:", Dev, "Event:", .Path_.Class
   Select Case .Path_.Class
    Case "__InstanceCreationEvent"
      If deviceStat.Exists(Dev) then
       If deviceStat.Item(Dev) <> "Inserted" Then
        deviceStat.remove(Dev)
        devicestat.add Dev,"Inserted"
        OnInsert(deviceExec.Item(Dev))
       End If
      else
        devicestat.add Dev,"Inserted"
        OnInsert(deviceExec.Item(Dev))
      end if
    Case "__InstanceDeletionEvent"
     If deviceStat.Exists(Dev) then
      If deviceStat.Item(Dev) <> "Removed" Then
        deviceStat.remove(Dev)
        devicestat.add Dev,"Removed"
        OnRemove(deviceKill.Item(Dev))
      End If
     else
       devicestat.add Dev,"Removed" 
       OnRemove(deviceKill.Item(Dev))
     end if
    End Select
  End If
 End With
End Sub
  
Sub OnInsert(ExecCmd) 
	On Error Resume Next
	Set objClass = GetObject("winmgmts:{impersonationLevel=impersonate}!\\.\root\CIMV2:Win32_Process")
	If Err.Number <> 0 Then
		Log Err.Number & ": " & Err.Description
	End If
	Log " Executing: " & ExecCmd
	Res = objClass.Create(ExecCmd, Null, Null, PID)
	If Res <> 0 Then
		Log "Код ошибки: " & Res
	End If
	On Error GOTO 0
End Sub
  
Sub OnRemove(KillCmd)
	On Error Resume Next
	Set objClass = GetObject("winmgmts:{impersonationLevel=impersonate}!\\.\root\CIMV2")
	If Err.Number <> 0 Then
		Log Err.Number & ": " & Err.Description
	End If
	For Each objProc In objClass.ExecQuery("SELECT * FROM Win32_Process WHERE CommandLine LIKE '%" & KillCmd & "%'")
		Log " Terminating: " & objProc.CommandLine
		objProc.Terminate
	Next
	On Error GOTO 0
End Sub

Sub EnumerateDeviceByID(Id, ExecCMD, KillCMD)
  If Not deviceIDs.Exists(Id) Then
   deviceIDs.Add Id, Id
   deviceExec.Add Id, ExecCMD
   deviceKill.Add Id, KillCMD
   Log " Monitoring device: " & Id
  End If
End Sub

Sub EnumerateDeviceByName(name, ExecCMD, KillCMD)
 Set objDevices = objWMIService.ExecQuery("SELECT DeviceId FROM Win32_PnPSignedDriver WHERE Description='" & name & "'")
 For Each dev in objDevices
  deviceId = Left(dev.DeviceId,InStrRev(dev.DeviceId,"\")-1)
  If Not deviceIDs.Exists(deviceId) Then
   deviceIDs.Add deviceId, deviceId
   deviceExec.Add Id, ExecCMD
   deviceKill.Add Id, KillCMD
   WScript.Echo " Monitoring device: " & deviceId
  End If
 Next
End Sub

Function alreadyRunning()
 alreadyRunning = False
 wscrCount = ProcessCount( "%wscript%" & WScript.ScriptName & "%" )
 cscrCount = ProcessCount( "%cscript%" & WScript.ScriptName & "%" )
 If  wscrCount > 1 or cscrCount > 1 Then:alreadyRunning = True
End Function
  
Public Function ProcessCount(likestr)
 Set colItems = objWMIService.ExecQuery("SELECT Name,CommandLine FROM Win32_Process WHERE CommandLine Like '" & likestr & "'")
 ProcessCount = colItems.Count
End Function

Sub Log(text)
	WScript.Echo date(), time(), text
End Sub

If alreadyRunning() Then: WScript.Quit
  
Set objSink = WScript.CreateObject("WBemScripting.SWbemSink","event_")
Set objShell = CreateObject("WScript.Shell") 

Set deviceIDs = CreateObject("Scripting.Dictionary")
Set deviceExec = CreateObject("Scripting.Dictionary")
Set deviceKill = CreateObject("Scripting.Dictionary")
Set deviceStat = CreateObject("Scripting.Dictionary")

Log  "Init event hook..."
objWMIService.ExecNotificationQueryAsync objSink, "Select * from __InstanceOperationEvent within 1 where TargetInstance ISA 'Win32_PnPEntity'"  

Log  "Init devices..."
' Alladin HASP Key
'EnumerateDeviceByName "Aladdin USB Key","C:\2zserver\start.cmd","2zserver.exe"
EnumerateDeviceByID "USB\VID_0529&PID_0001","C:\2zserver\start.cmd","2zserver.exe"
' USB flash (test)
'EnumerateDeviceByID "USB\VID_058F&PID_6387","notepad.exe","notepad.exe"

Log "Monitoring..."
Do
 WScript.Sleep 1000
Loop
{% endhighlight %}

Работа скрипта сводится к следующему:

1. В самом начале создаём объект для работы с WMI

2. Вызов objWMIService.ExecNotificationQueryAsync привязывает событие в классе Win32_PnPEntity к ранее созданному объекту WBemScripting.SWbemSink

3. Как только происходит событие в привязанном классе Win32_PnPEntity, асинхронно вызывается функция с указанным нами префиксом event_ и именем OnObjectReady, где мы уже и обрабатываем события WMI, нужные нам. В данном случае, это будут события "__InstanceCreationEvent" и "__InstanceDeletionEvent", которые для класса Win32_PnPEntity как раз и означают, соответственно, появление или исчезновение какого-то устройства. При первом событии мы порождаем процесс, при втором процесс убиваем. 

Вся настройка скрипта сводится к вызову процедуры EnumerateDeviceByID, которой мы передаём в качестве параметров:

+ ID устройства, 
+ программу для запуска отрабатывающую по событию устройства __InstanceCreationEvent, 
+ имя процесса для удаления, отрабатывающую по событию "__InstanceDeletionEvent". 
 
К примеру, что бы при установке определённой флешки с ID="USB\VID_058F&PID_6387" открывался блокнот достаточно раскомментировать строчку:

{% highlight vb linenos %}
call EnumerateDeviceByID("USB\VID_058F&PID_6387","notepad.exe","notepad.exe")
{% endhighlight %}

#Результаты
Чего хотел, того и добился. Донгл вытаскивают - вставляют. Мне не надо лезть удалённо и делать что-то руками. Всё автоматизированно и ленивый админ может потратить освободившееся время рационально и с пользой. 

Данный скрипт выручал меня уже несколько раз, и если ПК без монитора и клавиатуры не так часто приходится обслуживать, то пробрасывать USB-устройства на виртуалки с помошью софтовых USB-IP решений задача нынче более распространённая. И скрипт помогал справиться с нестабильным поведением таких конструкций.

