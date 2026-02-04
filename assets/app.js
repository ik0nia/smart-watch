document.addEventListener("DOMContentLoaded", () => {
  const replaceAllSafe = (text, search, replacement) =>
    text.split(search).join(replacement);

  const copyButton = document.querySelector("[data-copy-target]");
  if (copyButton) {
    copyButton.addEventListener("click", async () => {
      const targetSelector = copyButton.getAttribute("data-copy-target");
      const target = targetSelector
        ? document.querySelector(targetSelector)
        : null;
      if (!target) return;
      try {
        await navigator.clipboard.writeText(target.textContent.trim());
        copyButton.textContent = "Copiat!";
        setTimeout(() => {
          copyButton.textContent = "Copiaza JSON";
        }, 1500);
      } catch (error) {
        copyButton.textContent = "Nu s-a putut copia";
      }
    });
  }

  document.querySelectorAll("form[data-template]").forEach((form) => {
    const template = form.dataset.template || "";
    const previewSelector = form.dataset.preview || "";
    const preview = previewSelector ? document.querySelector(previewSelector) : null;
    if (!template || !preview) return;

    const inputs = form.querySelectorAll("input, select, textarea");

    const updatePreview = () => {
      let output = template;
      inputs.forEach((input) => {
        const nameMatch = input.name.match(/fields\[[^\]]+\]\[([^\]]+)\]/);
        if (!nameMatch) return;
        const fieldName = nameMatch[1];
        let value = "";
        if (input.type === "checkbox") {
          const onValue = input.dataset.valueOn || "1";
          const offValue = input.dataset.valueOff || "0";
          value = input.checked ? onValue : offValue;
        } else {
          value = input.value.trim();
        }
        const placeholder = `{${fieldName}}`;
        output = replaceAllSafe(output, placeholder, value || placeholder);
      });
      const code = preview.querySelector("code");
      if (code) {
        code.textContent = output;
      }
    };

    inputs.forEach((input) => {
      input.addEventListener("input", updatePreview);
      input.addEventListener("change", updatePreview);
    });

    updatePreview();
  });

  const commandPreset = document.getElementById("command_preset");
  const commandForm = document.getElementById("command_form");
  const commandPayload = document.getElementById("payload_text");
  const commandMode = document.getElementById("command_mode");
  const queueMode = document.getElementById("queue_mode");
  const timeoutInput = document.getElementById("timeout");
  const confirmDangerous = document.getElementById("confirm_dangerous");

  let presetList = [];
  if (commandPreset && commandPreset.dataset.presets) {
    try {
      presetList = JSON.parse(commandPreset.dataset.presets);
    } catch (error) {
      presetList = [];
    }
  }
  if (commandForm && !commandForm.dataset.dangerousPreset) {
    commandForm.dataset.dangerousPreset = "0";
  }

  const getDangerousKeywords = () => {
    if (!commandForm || !commandForm.dataset.dangerousKeywords) return [];
    try {
      return JSON.parse(commandForm.dataset.dangerousKeywords) || [];
    } catch (error) {
      return [];
    }
  };

  const isPayloadDangerous = (payload) => {
    const keywords = getDangerousKeywords();
    if (!payload) return false;
    const upper = payload.toUpperCase();
    return keywords.some((keyword) => keyword && upper.includes(keyword.toUpperCase()));
  };

  const updateDangerousState = () => {
    if (!commandForm || !confirmDangerous || !commandPayload) return;
    const presetDanger = commandForm.dataset.dangerousPreset === "1";
    const dangerous =
      presetDanger || isPayloadDangerous(commandPayload.value.trim());
    confirmDangerous.required = dangerous;
    commandForm.dataset.dangerous = dangerous ? "1" : "0";
  };

  if (commandPreset) {
    commandPreset.addEventListener("change", () => {
      const index = Number(commandPreset.value);
      if (!Number.isNaN(index) && presetList[index]) {
        const preset = presetList[index];
        if (commandPayload && preset.payload) commandPayload.value = preset.payload;
        if (commandMode && preset.mode) commandMode.value = preset.mode;
        if (queueMode && preset.queue) queueMode.value = preset.queue;
        if (timeoutInput && preset.timeout !== undefined && preset.timeout !== null) {
          timeoutInput.value = preset.timeout;
        }
        if (commandForm) {
          commandForm.dataset.dangerousPreset = preset.dangerous ? "1" : "0";
        }
        if (confirmDangerous && preset.dangerous) confirmDangerous.checked = false;
        updateDangerousState();
      }
    });
  }

  if (commandPayload) {
    commandPayload.addEventListener("input", updateDangerousState);
    updateDangerousState();
  }

  if (commandForm) {
    commandForm.addEventListener("submit", (event) => {
      const dangerous = commandForm.dataset.dangerous === "1";
      if (dangerous && confirmDangerous && !confirmDangerous.checked) {
        event.preventDefault();
        alert("Confirmarea pentru comanda periculoasa este obligatorie.");
        return;
      }
      if (dangerous) {
        const ok = window.confirm("Esti sigur ca vrei sa trimiti aceasta comanda?");
        if (!ok) {
          event.preventDefault();
        }
      }
    });
  }

  const dashboard = document.querySelector("[data-telemetry-endpoint]");
  if (dashboard) {
    const endpoint = dashboard.dataset.telemetryEndpoint;
    const refreshSelect = document.querySelector("[data-refresh-select]");
    let refreshTimer = null;
    let mapInstance = null;
    let mapMarker = null;

    const getValue = (data, path) => {
      if (!data) return null;
      const segments = path.split(".");
      let value = data;
      for (const segment of segments) {
        if (value && typeof value === "object" && segment in value) {
          value = value[segment];
        } else {
          return null;
        }
      }
      return value;
    };

    const updateMap = (lat, lon) => {
      if (!window.L || !document.getElementById("map")) return;
      const coords = [lat, lon];
      if (!mapInstance) {
        mapInstance = window.L.map("map").setView(coords, 14);
        window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
          maxZoom: 18,
          attribution: "&copy; OpenStreetMap",
        }).addTo(mapInstance);
        mapMarker = window.L.marker(coords).addTo(mapInstance);
      } else {
        mapInstance.setView(coords, 14);
        if (mapMarker) {
          mapMarker.setLatLng(coords);
        }
      }
    };

    const updateTelemetry = (snapshot) => {
      document.querySelectorAll("[data-telemetry]").forEach((element) => {
        const key = element.dataset.telemetry;
        if (key === "status_badge") return;
        const value = getValue(snapshot, key);
        if (key === "is_online" && typeof value === "boolean") {
          element.textContent = value ? "Da" : "Nu";
          return;
        }
        if (value === null || value === undefined || value === "") {
          element.textContent = "—";
        } else {
          element.textContent = value;
        }
      });

      const badge = document.querySelector("[data-telemetry='status_badge']");
      if (badge) {
        if (snapshot.is_online) {
          badge.textContent = "Online";
          badge.classList.add("online");
          badge.classList.remove("offline");
        } else {
          badge.textContent = `Ultima activitate: ${snapshot.last_seen_human || "—"}`;
          badge.classList.remove("online");
          badge.classList.add("offline");
        }
      }

      const gpsSection = document.querySelector("[data-section='gps']");
      const lbsSection = document.querySelector("[data-section='lbs']");
      const hasGps =
        snapshot.gps &&
        typeof snapshot.gps.lat === "number" &&
        typeof snapshot.gps.lon === "number";

      if (gpsSection && lbsSection) {
        gpsSection.hidden = !hasGps;
        lbsSection.hidden = hasGps;
      }
      if (hasGps) {
        updateMap(snapshot.gps.lat, snapshot.gps.lon);
      }

      const stepsNote = document.querySelector(".steps-note");
      const stepsValue = snapshot.steps;
      const missingSteps =
        stepsValue === null ||
        stepsValue === undefined ||
        (Number.isFinite(stepsValue) && Number(stepsValue) === 0);
      if (stepsNote) {
        stepsNote.hidden = !missingSteps;
      }
    };

    const fetchTelemetry = async () => {
      if (!endpoint) return;
      try {
        const response = await fetch(endpoint, {
          credentials: "same-origin",
          headers: { "Accept": "application/json" },
        });
        if (!response.ok) return;
        const data = await response.json();
        if (data && data.snapshot) {
          updateTelemetry(data.snapshot);
        }
      } catch (error) {
        // ignore fetch errors
      }
    };

    const setRefresh = () => {
      if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
      }
      const value = refreshSelect ? Number(refreshSelect.value) : 0;
      if (value && value > 0) {
        refreshTimer = setInterval(fetchTelemetry, value * 1000);
      }
    };

    if (refreshSelect) {
      refreshSelect.addEventListener("change", setRefresh);
      setRefresh();
    }
  }
});
