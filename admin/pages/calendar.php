<!-- Naptár oldal -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-calendar-alt mr-2"></i>Naptár</h1>
            </div>
            <div class="col-sm-6 text-sm-right">
                <button class="btn btn-sm btn-primary" onclick="showAddEventModal()">
                    <i class="fas fa-plus mr-1"></i>Új esemény
                </button>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</section>

<!-- Esemény létrehozás/szerkesztés modal -->
<div class="modal fade" id="event-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="event-modal-title">Új esemény</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="event-id">
                <div class="form-group">
                    <label>Típus</label>
                    <select id="event-type" class="form-control">
                        <option value="block">Blokkolt idő</option>
                        <option value="travel">Utazás</option>
                        <option value="appointment">Időpont</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Megnevezés</label>
                    <input type="text" id="event-title" class="form-control" placeholder="pl. Szabadság, Kiszállás...">
                </div>
                <div class="form-group">
                    <label>Dátum</label>
                    <input type="date" id="event-date" class="form-control">
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label>Kezdés</label>
                            <input type="time" id="event-start" class="form-control" value="09:00" step="3600">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label>Befejezés</label>
                            <input type="time" id="event-end" class="form-control" value="10:00" step="3600">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Megjegyzés</label>
                    <textarea id="event-notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger mr-auto" id="event-delete-btn" style="display:none" onclick="deleteEvent()">
                    <i class="fas fa-trash mr-1"></i>Törlés
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Mégse</button>
                <button type="button" class="btn btn-primary" onclick="saveEvent()">
                    <i class="fas fa-save mr-1"></i>Mentés
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let calendar;

async function init_calendar() {
    const calendarEl = document.getElementById('calendar');

    calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'hu',
        initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'timeGridWeek,timeGridDay'
        },
        slotMinTime: '07:00:00',
        slotMaxTime: '19:00:00',
        slotDuration: '01:00:00',
        allDaySlot: false,
        nowIndicator: true,
        selectable: true,
        editable: false,
        height: 'auto',
        businessHours: {
            daysOfWeek: [1, 2, 3, 4, 5],
            startTime: '08:00',
            endTime: '17:00'
        },

        // Események betöltése API-ból
        events: async function(info, successCallback, failureCallback) {
            const start = info.startStr.split('T')[0];
            const end = info.endStr.split('T')[0];
            const data = await VV.get(`calendar/events?start=${start}&end=${end}`);
            if (data && data.success) {
                successCallback(data.data);
            } else {
                failureCallback();
            }
        },

        // Kattintás üres helyre → új esemény
        select: function(info) {
            showAddEventModal(info.startStr, info.endStr);
        },

        // Kattintás meglévő eseményre → szerkesztés
        eventClick: function(info) {
            const evt = info.event;
            const props = evt.extendedProps || {};

            // Ha megrendeléshez kapcsolt, nyissuk meg a megrendelést
            if (props.order_id) {
                navigateTo('orders');
                setTimeout(() => window.showOrder && window.showOrder(props.order_id), 300);
                return;
            }

            // Egyébként szerkesztő modal
            document.getElementById('event-modal-title').textContent = 'Esemény szerkesztése';
            document.getElementById('event-id').value = evt.id;
            document.getElementById('event-type').value = props.event_type || 'block';
            document.getElementById('event-title').value = evt.title;
            document.getElementById('event-date').value = evt.startStr.split('T')[0];
            document.getElementById('event-start').value = evt.startStr.split('T')[1]?.substring(0, 5) || '09:00';
            document.getElementById('event-end').value = evt.endStr?.split('T')[1]?.substring(0, 5) || '10:00';
            document.getElementById('event-notes').value = props.notes || '';
            document.getElementById('event-delete-btn').style.display = '';

            $('#event-modal').modal('show');
        },

        // Responsive
        windowResize: function(view) {
            if (window.innerWidth < 768) {
                calendar.changeView('timeGridDay');
            }
        }
    });

    calendar.render();
}

function showAddEventModal(startStr, endStr) {
    document.getElementById('event-modal-title').textContent = 'Új esemény';
    document.getElementById('event-id').value = '';
    document.getElementById('event-type').value = 'block';
    document.getElementById('event-title').value = '';
    document.getElementById('event-notes').value = '';
    document.getElementById('event-delete-btn').style.display = 'none';

    if (startStr) {
        document.getElementById('event-date').value = startStr.split('T')[0];
        document.getElementById('event-start').value = startStr.split('T')[1]?.substring(0, 5) || '09:00';
        document.getElementById('event-end').value = endStr?.split('T')[1]?.substring(0, 5) || '10:00';
    } else {
        document.getElementById('event-date').value = new Date().toISOString().split('T')[0];
        document.getElementById('event-start').value = '09:00';
        document.getElementById('event-end').value = '10:00';
    }

    $('#event-modal').modal('show');
}

async function saveEvent() {
    const id = document.getElementById('event-id').value;
    const payload = {
        event_type: document.getElementById('event-type').value,
        title: document.getElementById('event-title').value || document.getElementById('event-type').selectedOptions[0].text,
        event_date: document.getElementById('event-date').value,
        start_time: document.getElementById('event-start').value,
        end_time: document.getElementById('event-end').value,
        notes: document.getElementById('event-notes').value
    };

    if (!payload.event_date || !payload.start_time || !payload.end_time) {
        VV.toast('Töltse ki a kötelező mezőket.', 'error');
        return;
    }

    let res;
    if (id) {
        res = await VV.put(`calendar/events/${id}`, payload);
    } else {
        res = await VV.post('calendar/events', payload);
    }

    if (res && res.success) {
        VV.toast(id ? 'Esemény frissítve.' : 'Esemény létrehozva.', 'success');
        $('#event-modal').modal('hide');
        calendar.refetchEvents();
    } else {
        VV.toast(res?.message || 'Hiba történt.', 'error');
    }
}

async function deleteEvent() {
    const id = document.getElementById('event-id').value;
    if (!id) return;
    if (!await VV.confirm('Biztosan törli az eseményt?')) return;

    const res = await VV.del(`calendar/events/${id}`);
    if (res && res.success) {
        VV.toast('Esemény törölve.', 'success');
        $('#event-modal').modal('hide');
        calendar.refetchEvents();
    }
}

init_calendar();
</script>
