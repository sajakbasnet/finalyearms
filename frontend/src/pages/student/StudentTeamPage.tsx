import { useEffect, useState, type FormEvent } from 'react'
import { extractError, fetchStudents, type StudentListItem } from '../../api/admin'
import {
  acceptInvitation,
  createProject,
  declineInvitation,
  fetchInvitations,
  fetchTeam,
  inviteMember,
  removeMember,
  transferLead,
  type Invitation,
  type Team,
} from '../../api/team'
import { useAuth } from '../../contexts/AuthContext'
import { Modal } from '../../components/Modal'

export function StudentTeamPage() {
  const { user } = useAuth()
  const [team, setTeam] = useState<Team | null>(null)
  const [invitations, setInvitations] = useState<Invitation[]>([])
  const [classmates, setClassmates] = useState<StudentListItem[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const [projectForm, setProjectForm] = useState({ title: '', is_team: true, team_name: '' })
  const [isProjectModalOpen, setIsProjectModalOpen] = useState(false)
  const [isInviteModalOpen, setIsInviteModalOpen] = useState(false)
  const [inviteId, setInviteId] = useState('')
  const [inviteMessage, setInviteMessage] = useState('')

  async function load() {
    setIsLoading(true)
    try {
      const [teamData, invites] = await Promise.all([fetchTeam(), fetchInvitations()])
      setTeam(teamData)
      setInvitations(invites)
    } catch (err) {
      setError(extractError(err, 'Could not load your team.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void load()
    void fetchStudents({}).then((page) => setClassmates(page.data)).catch(() => undefined)
  }, [])

  function announce(message: string) {
    setSuccess(message)
    setError(null)
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault()
    setError(null)

    try {
      await createProject({
        title: projectForm.title,
        is_team: projectForm.is_team,
        team_name: projectForm.team_name || undefined,
      })
      setIsProjectModalOpen(false)
      announce(
        projectForm.is_team
          ? 'Team created. Invite members, then request a supervisor.'
          : 'Individual project created. You can request a supervisor now.',
      )
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not create the project.'))
    }
  }

  async function handleInvite(event: FormEvent) {
    event.preventDefault()
    setError(null)

    try {
      await inviteMember({
        student_id: Number(inviteId),
        message: inviteMessage || undefined,
      })
      setInviteId('')
      setInviteMessage('')
      setIsInviteModalOpen(false)
      announce('Invitation sent successfully.')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not send the invitation.'))
    }
  }

  async function respond(invitation: Invitation, accept: boolean) {
    setError(null)

    try {
      if (accept) {
        await acceptInvitation(invitation.id)
        announce('You have joined the team.')
      } else {
        await declineInvitation(invitation.id)
        announce('Invitation declined.')
      }
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not respond to the invitation.'))
    }
  }

  async function handleRemove(studentId: number, isSelf: boolean) {
    if (!window.confirm(isSelf ? 'Leave this team?' : 'Remove this member?')) {
      return
    }

    try {
      await removeMember(studentId)
      announce(isSelf ? 'You have left the team.' : 'Member removed.')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not remove member.'))
    }
  }

  async function handleTransfer(studentId: number) {
    if (!window.confirm('Transfer team leadership to this student?')) {
      return
    }

    try {
      await transferLead(studentId)
      announce('Leadership transferred.')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not transfer leadership.'))
    }
  }

  const openInvitations = invitations.filter((i) => i.status === 'pending')

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">My Team & Project</h1>
          <p className="mt-1 text-slate-500">
            Form your team, invite peers, and assemble members before requesting a supervisor.
          </p>
        </div>
        {team === null ? (
          <button
            type="button"
            onClick={() => setIsProjectModalOpen(true)}
            className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 active:bg-blue-800"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
            </svg>
            Start a Project
          </button>
        ) : (
          team.is_lead &&
          team.member_count < team.max_members && (
            <button
              type="button"
              onClick={() => setIsInviteModalOpen(true)}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 active:bg-blue-800"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
              </svg>
              Invite Member
            </button>
          )
        )}
      </div>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
          {error}
        </div>
      )}
      {success && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
          {success}
        </div>
      )}

      {/* Invitations Alert */}
      {openInvitations.length > 0 && (
        <div className="rounded-2xl border border-blue-200 bg-blue-50/70 p-5 shadow-sm">
          <div className="flex items-center gap-2 text-blue-900 font-bold text-base">
            <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            Pending Team Invitations ({openInvitations.length})
          </div>
          <ul className="mt-3 grid gap-3 sm:grid-cols-2">
            {openInvitations.map((invitation) => (
              <li
                key={invitation.id}
                className="flex flex-col justify-between rounded-xl border border-blue-100 bg-white p-4 shadow-sm"
              >
                <div>
                  <p className="font-bold text-slate-900">{invitation.team.name}</p>
                  <p className="text-xs text-slate-500 mt-1">
                    Invited by <span className="font-semibold text-slate-700">{invitation.invited_by}</span> · {invitation.team.member_count} member(s)
                  </p>
                  {invitation.message && (
                    <p className="mt-2 text-xs italic text-slate-600 bg-slate-50 p-2 rounded-lg border border-slate-100">
                      &ldquo;{invitation.message}&rdquo;
                    </p>
                  )}
                </div>
                <div className="mt-4 flex gap-2 justify-end border-t border-slate-100 pt-3">
                  <button
                    type="button"
                    onClick={() => void respond(invitation, false)}
                    className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 transition"
                  >
                    Decline
                  </button>
                  <button
                    type="button"
                    onClick={() => void respond(invitation, true)}
                    className="rounded-lg bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition"
                  >
                    Accept & Join
                  </button>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}

      {isLoading ? (
        <div className="rounded-xl border border-slate-200 bg-white py-12 text-center text-slate-400 shadow-sm">
          Loading team workspace…
        </div>
      ) : team === null ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center shadow-sm">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
            <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <h2 className="mt-4 text-xl font-bold text-slate-900">You do not have an active team yet</h2>
          <p className="mt-1 text-sm text-slate-500 max-w-md mx-auto">
            You can form a group project with classmates (4–6 students) or register an individual capstone project.
          </p>
          <button
            type="button"
            onClick={() => setIsProjectModalOpen(true)}
            className="mt-6 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition"
          >
            Start a Project Now
          </button>
        </div>
      ) : (
        <div className="space-y-6">
          {/* Team Overview Card */}
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div>
                <span className="text-xs font-bold uppercase tracking-wider text-blue-600">Team Workspace</span>
                <h2 className="text-2xl font-bold text-slate-900 mt-0.5">{team.name}</h2>
                <div className="mt-2 flex flex-wrap items-center gap-3 text-sm">
                  <span className="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 border border-blue-100">
                    {team.member_count} of {team.min_members}–{team.max_members} Members
                  </span>
                  {team.supervisor ? (
                    <span className="inline-flex items-center gap-1.5 text-emerald-700 font-medium text-xs">
                      <span className="h-2 w-2 rounded-full bg-emerald-500"></span>
                      Supervised by {team.supervisor.name}
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1.5 text-amber-700 font-medium text-xs">
                      <span className="h-2 w-2 rounded-full bg-amber-500"></span>
                      No supervisor assigned yet
                    </span>
                  )}
                </div>
              </div>

              {team.is_lead && team.member_count < team.max_members && (
                <button
                  type="button"
                  onClick={() => setIsInviteModalOpen(true)}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition"
                >
                  + Invite Member
                </button>
              )}
            </div>

            {team.member_count < team.min_members && (
              <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50/80 p-3.5 text-xs font-medium text-amber-900 flex items-center gap-2.5">
                <svg className="w-5 h-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>
                  This team needs at least {team.min_members} members before you can send a formal request to a faculty supervisor.
                </span>
              </div>
            )}
          </div>

          {/* Members Table */}
          <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 bg-slate-50/80 px-6 py-4">
              <h3 className="text-sm font-bold uppercase tracking-wider text-slate-700">Team Roster</h3>
            </div>
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="px-6 py-3.5">Student Member</th>
                  <th className="px-6 py-3.5">Registration No.</th>
                  <th className="px-6 py-3.5">Role</th>
                  <th className="px-6 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {team.members.map((member) => {
                  const isSelf = member.name === user?.name
                  return (
                    <tr key={member.student_id} className="hover:bg-blue-50/30 transition">
                      <td className="px-6 py-4 font-semibold text-slate-900">
                        {member.name} {isSelf && <span className="text-xs font-normal text-slate-400">(You)</span>}
                      </td>
                      <td className="px-6 py-4 font-mono text-xs text-slate-600">
                        {member.registration_number}
                      </td>
                      <td className="px-6 py-4">
                        {member.is_leader ? (
                          <span className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 border border-blue-200">
                            Team Lead
                          </span>
                        ) : (
                          <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                            Member
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-right whitespace-nowrap">
                        <div className="inline-flex items-center gap-2">
                          {team.is_lead && !member.is_leader && (
                            <button
                              type="button"
                              onClick={() => void handleTransfer(member.student_id)}
                              className="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                              Transfer Lead
                            </button>
                          )}
                          {(team.is_lead || isSelf) && !member.is_leader && (
                            <button
                              type="button"
                              onClick={() => void handleRemove(member.student_id, isSelf)}
                              className="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 transition"
                            >
                              {isSelf ? 'Leave Team' : 'Remove'}
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {/* Pending Invitations list */}
          {team.pending_invitations.length > 0 && (
            <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                Outgoing Invitations Awaiting Response ({team.pending_invitations.length})
              </h3>
              <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {team.pending_invitations.map((invite) => (
                  <div key={invite.id} className="rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs">
                    <p className="font-semibold text-slate-800">{invite.name}</p>
                    <p className="text-slate-500 font-mono mt-0.5">{invite.registration_number}</p>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Modal: Create Project / Team */}
      <Modal
        isOpen={isProjectModalOpen}
        onClose={() => setIsProjectModalOpen(false)}
        title="Start a Project"
        subtitle="Initialize your final year project workspace."
      >
        <form onSubmit={handleCreate} className="space-y-4">
          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Project Title</span>
            <input
              required
              placeholder="e.g. Distributed Sensor Network for Agriculture"
              value={projectForm.title}
              onChange={(e) => setProjectForm((p) => ({ ...p, title: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </label>

          <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
            <label className="flex items-center gap-3 text-sm font-semibold text-slate-800 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={projectForm.is_team}
                onChange={(e) => setProjectForm((p) => ({ ...p, is_team: e.target.checked }))}
                className="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4"
              />
              <span>This is a Group Project (4–6 students)</span>
            </label>
            <p className="text-xs text-slate-500 mt-1 pl-7">
              Uncheck if this is an individual project where only you will contribute.
            </p>
          </div>

          {projectForm.is_team && (
            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Team Name (Optional)</span>
              <input
                placeholder="Defaults to project title if left blank"
                value={projectForm.team_name}
                onChange={(e) => setProjectForm((p) => ({ ...p, team_name: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>
          )}

          <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button
              type="button"
              onClick={() => setIsProjectModalOpen(false)}
              className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition"
            >
              Create Project
            </button>
          </div>
        </form>
      </Modal>

      {/* Modal: Invite Member */}
      <Modal
        isOpen={isInviteModalOpen}
        onClose={() => setIsInviteModalOpen(false)}
        title="Invite Classmate"
        subtitle="Send an official invitation to join your final year project team."
      >
        <form onSubmit={handleInvite} className="space-y-4">
          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Select Student</span>
            <select
              required
              value={inviteId}
              onChange={(e) => setInviteId(e.target.value)}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            >
              <option value="">Choose a classmate from your department</option>
              {classmates.map((student) => (
                <option key={student.id} value={student.id}>
                  {student.name} ({student.registration_number})
                </option>
              ))}
            </select>
          </label>

          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Invitation Note (Optional)</span>
            <textarea
              rows={3}
              placeholder="Hi! Would you like to join our final year project on this topic?"
              value={inviteMessage}
              onChange={(e) => setInviteMessage(e.target.value)}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </label>

          <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button
              type="button"
              onClick={() => setIsInviteModalOpen(false)}
              className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={!inviteId}
              className="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60 transition"
            >
              Send Invitation
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
